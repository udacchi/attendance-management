<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

use App\Models\AttendanceDay;
use App\Models\User;
use App\Models\CorrectionLog;
use App\Models\CorrectionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        // ▼ 1) 表示する基準日（?date=YYYY-MM-DD / ?month=YYYY-MM / データ最新日）
        $latest = AttendanceDay::max('work_date');
        if ($request->filled('month')) {
            $date = Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth();
        } elseif ($request->filled('date')) {
            $date = Carbon::parse($request->query('date'), $tz)->startOfDay();
        } elseif ($latest) {
            $date = Carbon::parse($latest, $tz)->startOfDay(); // ← 明示
        } else {
            $date = Carbon::now($tz)->startOfDay();
        }

        // 既存の絞り込み（必要ならそのまま維持）
        $userId   = $request->query('user_id');
        $dateFrom = $request->query('from');
        $dateTo   = $request->query('to');

        // ▼ 2) ページネーション付きの全件一覧（そのまま）
        $attendances = AttendanceDay::query()
            ->when($userId,  fn($q) => $q->where('user_id', $userId))
            ->when($dateFrom, fn($q) => $q->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('work_date', '<=', $dateTo))
            ->with('user:id,name')
            ->orderByDesc('work_date')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // ▼ 3) その1日分を Blade の $records 形式に整形
        $fmtTime = function ($v) {
            if (empty($v)) return '–';
            if ($v instanceof \DateTimeInterface) return $v->format('H:i');
            if (is_string($v) && preg_match('/^\d{1,2}:\d{2}/', $v)) return substr($v, 0, 5);
            try {
                return Carbon::parse($v)->format('H:i');
            } catch (\Throwable $e) {
                return '–';
            }
        };

        $minutesToHMM = function ($mins) {
            if ($mins === null) return '–';
            $h = intdiv((int)$mins, 60);
            $m = ((int)$mins) % 60;
            return sprintf('%d:%02d', $h, $m);
        };

        $dayItems = AttendanceDay::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereDate('work_date', $date->toDateString())
            ->with('user:id,name')
            ->orderBy('user_id')
            ->get();

        $records = $dayItems->map(function ($a) use ($fmtTime, $minutesToHMM) {

            // ★ 出勤・退勤どちらか1つでも入っていれば「打刻あり」とみなす
            $hasStamp = ($a->clock_in_at !== null) || ($a->clock_out_at !== null);

            return [
                'id'          => $a->id,
                'user_id'     => $a->user_id,
                'name'        => optional($a->user)->name ?? '—',
                'clock_in'    => $fmtTime($a->clock_in_at),
                'clock_out'   => $fmtTime($a->clock_out_at),

                // ★ 両方とも未打刻のときは休憩を「－」固定
                'break_total' => $hasStamp ? $minutesToHMM($a->break_minutes) : '–',

                // 合計はこれまで通りアクセサ経由（未打刻なら null → minutesToHMM で "–"）
                'work_total'  => $minutesToHMM($a->work_minutes),
            ];
        })->values();



        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        return view('admin.attendance.list', compact(
            'date',
            'records',                 // ★ 追加
            'attendances',
            'users',
            'userId',
            'dateFrom',
            'dateTo'
        ));
    }

    public function show(AttendanceDay $attendanceDay, Request $request)
    {
        $date = $request->query('date') ?? $attendanceDay->work_date;
        return redirect()->route('admin.attendance.detail', [
            'id'   => $attendanceDay->user_id,
            'date' => $date,
        ]);
    }

    public function detailByUserDate(int $id, Request $request)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $user = User::findOrFail($id);

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            []
        );
        $attendanceDay->load(['breakPeriods']);

        // 承認待ちロック
        $isLocked = $this->hasPendingCorrection($user->id, $date)
            ? (function () use ($user, $date) {
                $q = \App\Models\CorrectionRequest::query();

                // ユーザー列名の自動判定
                $userCol = Schema::hasColumn('correction_requests', 'requested_user_id') ? 'requested_user_id'
                    : (Schema::hasColumn('correction_requests', 'requested_by') ? 'requested_by'
                        : (Schema::hasColumn('correction_requests', 'user_id') ? 'user_id' : null));

                // 対象日付列名の自動判定
                $dateCol = Schema::hasColumn('correction_requests', 'work_date') ? 'work_date'
                    : (Schema::hasColumn('correction_requests', 'target_date') ? 'target_date'
                        : (Schema::hasColumn('correction_requests', 'date') ? 'date' : null));

                if (!$userCol || !$dateCol) {
                    // 列が見つからない場合はロック無効（落とさない）
                    return false;
                }

                return $q->where($userCol, $user->id)
                    ->whereDate($dateCol, $date->toDateString())
                    ->where('status', 'pending')
                    ->exists();
            })()
            : false;

        return view('admin.attendance.detail', [
            'user'   => $user,
            'date'   => $date,
            'record' => [
                'user_id'     => $user->id,
                'name'        => $user->name,
                'date'        => $date->toDateString(),
                'clock_in'    => $attendanceDay->clock_in_at
                    ? Carbon::parse($attendanceDay->clock_in_at)->format('H:i') : '–',
                'clock_out'   => $attendanceDay->clock_out_at
                    ? Carbon::parse($attendanceDay->clock_out_at)->format('H:i') : '–',
                'break_total' => '–',
                'work_total'  => '–',
                'note'        => $attendanceDay->note,
            ],
            'attendanceDay' => $attendanceDay,
            'isLocked'      => $isLocked, // ★ 画面側で入力無効・メッセージ表示に使用（FN038）
        ]);
    }

    // 編集フォーム（user+date ベース）
    public function editByUserDate(Request $request, User $user)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        // その日のレコードが無ければ作る（管理者の直接修正対象を必ず用意）
        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            [] // 初期値は空でOK（打刻なし状態）
        );

        $attendanceDay->load('breakPeriods'); // 必要なら

        return $this->detailByUserDate($user->id, $request);
    }

    // 更新（管理者が直接反映：FN040、要件FN039のバリデーションを内蔵）
    public function updateByUserDate(Request $request, User $user)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        // 二重防御：承認待ちは更新不可（FN038）
        
        if ($this->hasPendingCorrection($user->id, $date)) {
            return back()->with('error', '承認待ちのため修正はできません。')->withInput();
        }

        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            []
        );

        // ===== FN039: バリデーション（HH:MM 形式 + 論理チェック + 備考必須）=====
        $rules = [
            'clock_in'      => ['nullable', 'date_format:H:i'],
            'clock_out'     => ['nullable', 'date_format:H:i'],
            'break1_start'  => ['nullable', 'date_format:H:i'],
            'break1_end'    => ['nullable', 'date_format:H:i'],
            'break2_start'  => ['nullable', 'date_format:H:i'],
            'break2_end'    => ['nullable', 'date_format:H:i'],
            'note'          => ['required', 'string', 'max:1000'], // 4) 備考必須
        ];
        $messages = [
            'note.required' => '備考を記入してください',
        ];
        $data = $request->validate($rules, $messages);

        // 追加の相関チェック（出退勤前後・休憩の範囲）
        $toDT = function (?string $hm) use ($date) {
            return $hm ? Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $hm) : null;
        };
        $in   = $toDT($request->input('clock_in'));
        $out  = $toDT($request->input('clock_out'));
        $b1s  = $toDT($request->input('break1_start'));
        $b1e  = $toDT($request->input('break1_end'));
        $b2s  = $toDT($request->input('break2_start'));
        $b2e  = $toDT($request->input('break2_end'));

        $errors = [];
        // 1) 出勤 > 退勤
        if ($in && $out && $in->gt($out)) {
            $errors[] = '出勤時間もしくは退勤時間が不適切な値です';
        }
        // 2) 休憩開始は出勤〜退勤の間
        foreach ([['break1_start', $b1s], ['break2_start', $b2s]] as [$field, $start]) {
            if ($start && (($in && $start->lt($in)) || ($out && $start->gt($out)))) {
                $errors[] = '休憩時間が不適切な値です';
                break;
            }
        }
        // 3) 休憩終了は対応開始より後、かつ退勤を超えない
        foreach ([['break1_end', $b1e, $b1s], ['break2_end', $b2e, $b2s]] as [$field, $end, $start]) {
            if ($end && (($start && $end->lte($start)) || ($out && $end->gt($out)))) {
                $errors[] = '休憩時間もしくは退勤時間が不適切な値です';
                break;
            }
        }
        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }
        // ===== /FN039 =====

        DB::transaction(function () use ($attendanceDay, $in, $out, $b1s, $b1e, $b2s, $b2e, $request, $tz, $user, $date) {

            // ===== 休憩列名の自動検出（1回だけ判定）=====
            $tbl = 'break_periods';
            $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
                : (Schema::hasColumn($tbl, 'started_at')     ? 'started_at'
                    : (Schema::hasColumn($tbl, 'start_at')       ? 'start_at' : null));

            $colEnd   = Schema::hasColumn($tbl, 'break_end_at')   ? 'break_end_at'
                : (Schema::hasColumn($tbl, 'ended_at')       ? 'ended_at'
                    : (Schema::hasColumn($tbl, 'end_at')         ? 'end_at' : null));

            // 変更前スナップショット（★ここで先に作る）
            $attendanceDay->load('breakPeriods');
            $before = [
                'clock_in_at'  => $attendanceDay->clock_in_at,
                'clock_out_at' => $attendanceDay->clock_out_at,
                'breaks'       => $attendanceDay->breakPeriods?->map(function ($b) use ($colStart, $colEnd) {
                    return [
                        'start' => $colStart ? $b->{$colStart} : null,
                        'end'   => $colEnd   ? $b->{$colEnd}   : null,
                    ];
                })->values()->all() ?? [],
            ];

            // ---- 本体更新 ----
            $attendanceDay->clock_in_at  = $in;
            $attendanceDay->clock_out_at = $out;
            $attendanceDay->note         = (string)$request->input('note', '');
            $attendanceDay->save();

            // ---- 休憩の差し替え ----
            if (method_exists($attendanceDay, 'breakPeriods')) {
                $attendanceDay->breakPeriods()->delete();

                if ($colStart && $colEnd) {
                    foreach ([[$b1s, $b1e], [$b2s, $b2e]] as [$s, $e]) {
                        if ($s && $e && $e->gt($s)) {
                            $attendanceDay->breakPeriods()->create([
                                $colStart => $s->copy(),
                                $colEnd   => $e->copy(),
                            ]);
                        }
                    }
                }
            }

            // ---- 合計再計算（秒切り捨てで分保存）----
            $attendanceDay->load('breakPeriods');
            $breakSeconds = 0;
            foreach ($attendanceDay->breakPeriods as $bp) {
                $s = ($colStart && $bp->{$colStart}) ? Carbon::parse($bp->{$colStart}, $tz) : null;
                $e = ($colEnd   && $bp->{$colEnd})   ? Carbon::parse($bp->{$colEnd},   $tz) : null;
                if ($s && $e && $e->gt($s)) $breakSeconds += $e->diffInSeconds($s);
            }

            // ★ 退勤 < 出勤 のときは「翌日の退勤」とみなして計算する
            $adjustedOut = $out ? $out->copy() : null;
            if ($in && $adjustedOut && $adjustedOut->lt($in)) {
                $adjustedOut->addDay();   // 0時跨ぎ対応：退勤を+1日
            }

            // ★ 調整済みの退勤時刻で勤務秒数を計算
            $workSeconds = ($in && $adjustedOut)
                ? max(0, $adjustedOut->diffInSeconds($in) - $breakSeconds)
                : 0;

            if ($attendanceDay->isFillable('total_break_minutes')) {
                $attendanceDay->total_break_minutes = intdiv($breakSeconds, 60);
            }
            if ($attendanceDay->isFillable('total_work_minutes')) {
                $attendanceDay->total_work_minutes  = intdiv($workSeconds, 60);
            }
            if ($attendanceDay->isDirty()) $attendanceDay->save();

            // ---- 監査ログ（存在すれば・落ちない実装）----
            if (class_exists(CorrectionLog::class) && Schema::hasTable('correction_logs')) {
                $after = [
                    'clock_in_at'  => $attendanceDay->clock_in_at,
                    'clock_out_at' => $attendanceDay->clock_out_at,
                    'breaks'       => $attendanceDay->breakPeriods?->map(function ($b) use ($colStart, $colEnd) {
                        return [
                            'start' => $colStart ? $b->{$colStart} : null,
                            'end'   => $colEnd   ? $b->{$colEnd}   : null,
                        ];
                    })->values()->all() ?? [],
                ];

                $payload = [
                    'acted_by_admin_id' => Auth::guard('admin')->id(),
                    'user_id'           => $user->id,
                    'work_date'         => $date->toDateString(),
                    'before_json'       => json_encode($before, JSON_UNESCAPED_UNICODE),
                    'after_json'        => json_encode($after,  JSON_UNESCAPED_UNICODE),
                    'note'              => (string)$request->input('note', ''),
                ];
                if (Schema::hasColumn('correction_logs', 'correction_request_id')) {
                    $payload['correction_request_id'] = null; // 直接修正は申請に紐付かない
                }

                try {
                    CorrectionLog::create($payload);
                } catch (\Illuminate\Database\QueryException $e) {
                    // 失敗しても本処理は成功させる
                }
            }
        });

        // 戻り先に ?date= をきちんと付け直す（FN037 もれ防止）
        return redirect()
            ->to(route('admin.attendance.detail', ['id' => $user->id]) . '?date=' . $date->toDateString())
            ->with('status', '管理者による修正を反映しました。');
    }

    public function staff(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $tz = config('app.timezone', 'Asia/Tokyo');

        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        // 1) 空の一覧を作る
        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = [
                'date'        => $d->copy(),
                'clock_in'    => null,
                'clock_out'   => null,
                'break_total' => 0,
                'work_total'  => 0,
            ];
        }

        // 2) 月内の実データを取得（★ work_date で絞る）
        $records = \App\Models\AttendanceDay::query()
            ->with('breakPeriods')
            ->where('user_id', $user->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->get();

        // 3) 埋め戻し
        foreach ($records as $ad) {
            $key = $ad->work_date->toDateString();
            if (!isset($days[$key])) {
                // 範囲外安全策（11/04 などが混ざっても描画しない）
                continue;
            }
            $breakMin = $ad->breakPeriods->sum(function ($b) {
                $s = \Carbon\Carbon::parse($b->start_at);
                $e = $b->end_at ? \Carbon\Carbon::parse($b->end_at) : now();
                return $s->diffInMinutes($e);
            });

            $days[$key]['clock_in']    = optional($ad->clock_in_at)->format('H:i');
            $days[$key]['clock_out']   = optional($ad->clock_out_at)->format('H:i');
            $days[$key]['break_total'] = $breakMin;
            $days[$key]['work_total']  = (int)($ad->total_work_minutes ?? 0);
        }

        // ビューへ
        return view('admin.attendance.staff', [
            'user' => $user,
            'month' => $month,
            'days'  => collect($days)->values(), // 並びは 1日→末日
        ]);
    }


    public function staffCsv(\Illuminate\Http\Request $request, int $id): StreamedResponse
    {
        $tz    = config('app.timezone', 'Asia/Tokyo');
        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $user = \App\Models\User::findOrFail($id);

        // 月内の勤怠を取得（必要に応じて列名はあなたのスキーマに合わせて調整）
        $days = AttendanceDay::where('user_id', $id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $month->format('Y-m'));

        return response()->streamDownload(function () use ($days) {
            $out = fopen('php://output', 'w');
            // ヘッダ行（UTF-8 / ExcelならS-JISに変換する場合は iconv を使用）
            fputcsv($out, ['date', 'clock_in', 'clock_out', 'break_total', 'work_total']);

            foreach ($days as $d) {
                // あなたのカラム名に合わせて整形
                $date        = $d->work_date;
                $clockIn     = optional($d->clock_in_at)->format('H:i');
                $clockOut    = optional($d->clock_out_at)->format('H:i');
                $breakTotal  = $d->break_total_text ?? ''; // アクセサが無ければ自前で整形
                $workTotal   = $d->work_total_text  ?? '';

                fputcsv($out, [
                    $date,
                    $clockIn ?: '',
                    $clockOut ?: '',
                    $breakTotal,
                    $workTotal,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * correction_requests に「承認待ち」があるかを安全に判定（列名の差異に対応）
     */
    private function hasPendingCorrection(int $userId, Carbon $date): bool
    {
        // モデル・テーブルが無ければロックなしで進める
        if (!class_exists(\App\Models\CorrectionRequest::class)) return false;
        if (!Schema::hasTable('correction_requests')) return false;

        $tbl = 'correction_requests';

        // ユーザー列名を推定
        $userCol = Schema::hasColumn($tbl, 'requested_user_id') ? 'requested_user_id'
            : (Schema::hasColumn($tbl, 'requested_by') ? 'requested_by'
                : (Schema::hasColumn($tbl, 'user_id') ? 'user_id' : null));

        // 日付列名を推定
        $dateCol = Schema::hasColumn($tbl, 'work_date') ? 'work_date'
            : (Schema::hasColumn($tbl, 'target_date') ? 'target_date'
                : (Schema::hasColumn($tbl, 'date') ? 'date' : null));

        // status 列が無い等、判定できないならロック無しで通す
        if (!$userCol || !$dateCol || !Schema::hasColumn($tbl, 'status')) {
            return false;
        }

        return CorrectionRequest::where($userCol, $userId)
            ->whereDate($dateCol, $date->toDateString())
            ->where('status', 'pending')
            ->exists();
    }
}
