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
use App\Http\Requests\Admin\AttendanceUpdateRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        // ▼ 1) 表示日
        $latest = AttendanceDay::max('work_date');
        if ($request->filled('month')) {
            $date = Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth();
        } elseif ($request->filled('date')) {
            $date = Carbon::parse($request->query('date'), $tz)->startOfDay();
        } elseif ($latest) {
            $date = Carbon::parse($latest, $tz)->startOfDay();
        } else {
            $date = Carbon::now($tz)->startOfDay();
        }

        // ▼ 2) ページネーション
        $userId   = $request->query('user_id');
        $dateFrom = $request->query('from');
        $dateTo   = $request->query('to');

        $attendances = AttendanceDay::query()
            ->when($userId,  fn($q) => $q->where('user_id', $userId))
            ->when($dateFrom, fn($q) => $q->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('work_date', '<=', $dateTo))
            ->with('user:id,name')
            ->orderByDesc('work_date')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();


        // ▼ 3) 当日の全ユーザーの勤怠を user_id で引けるように
        $attByUser = AttendanceDay::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereDate('work_date', $date->toDateString())
            ->get()
            ->keyBy('user_id');

        // ▼ 4) 一覧に出すユーザー
        $users = User::query()
            ->when($userId, fn($q) => $q->where('id', $userId))
            ->when(Schema::hasColumn('users', 'role'), fn($q) => $q->where('role', 'user'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // ▼ 時刻フォーマット
        $fmtTime = function ($v) {
            if (empty($v)) return null;
            if ($v instanceof \DateTimeInterface) return $v->format('H:i');
            return Carbon::parse($v)->format('H:i');
        };
        $minutesToHMM = function ($mins) {
            if ($mins === null) return null;
            $mins = (int)$mins;
            return sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
        };

        // ▼ 5) 1日分の表示データ作成
        $records = $users->map(function ($u) use ($attByUser, $fmtTime, $minutesToHMM) {
            $a = $attByUser->get($u->id);

            // ★勤怠が無い場合は全部 null
            if (!$a) {
                return [
                    'id'          => null,
                    'user_id'     => $u->id,
                    'name'        => $u->name,
                    'clock_in'    => null,
                    'clock_out'   => null,
                    'break_total' => null,
                    'work_total'  => null,
                ];
            }

            // ★出勤 or 退勤が入力されているか判定
            $hasPunch = ($a->clock_in_at || $a->clock_out_at);

            // 合計分取得（null の場合あり）
            $breakMin = $a->total_break_minutes ?? $a->break_minutes ?? null;
            $workMin  = $a->total_work_minutes  ?? $a->work_minutes  ?? null;

            return [
                'id'          => $a->id,
                'user_id'     => $u->id,
                'name'        => $u->name,
                'clock_in'    => $fmtTime($a->clock_in_at),
                'clock_out'   => $fmtTime($a->clock_out_at),

                // ★ 重要：休憩 0:00 を出すのは「打刻がある時だけ」
                'break_total' => $hasPunch ? $minutesToHMM($breakMin ?? 0) : null,

                'work_total'  => is_null($workMin) ? null : $minutesToHMM($workMin),
            ];
        })->values();

        return view('admin.attendance.list', compact(
            'date',
            'records',
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

        // ===== 休憩レコードを breaks[] 形式に整形 =====
        $tbl = 'break_periods';
        $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
            : (Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                : (Schema::hasColumn($tbl, 'start_at') ? 'start_at' : null));
        $colEnd   = Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
            : (Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                : (Schema::hasColumn($tbl, 'end_at') ? 'end_at' : null));

        $breaks = [];

        if ($colStart && $colEnd) {
            foreach ($attendanceDay->breakPeriods->sortBy($colStart) as $bp) {
                $start = $bp->{$colStart} ?? null;
                $end   = $bp->{$colEnd}   ?? null;

                $breaks[] = [
                    'start' => $start ? Carbon::parse($start, $tz)->format('H:i') : '',
                    'end'   => $end   ? Carbon::parse($end,   $tz)->format('H:i') : '',
                ];
            }
        }

        // 末尾に必ず空行を 1 つ追加（常に「もう1件入力欄」を出す）
        $breaks[] = ['start' => '', 'end' => ''];

        // ===== 承認待ちロック（あなたの既存ロジックをそのまま流用） =====
        $isLocked = $this->hasPendingCorrection($user->id, $date)
            ? (function () use ($user, $date) {
                $q = CorrectionRequest::query();

                $userCol = Schema::hasColumn('correction_requests', 'requested_user_id') ? 'requested_user_id'
                    : (Schema::hasColumn('correction_requests', 'requested_by') ? 'requested_by'
                        : (Schema::hasColumn('correction_requests', 'user_id') ? 'user_id' : null));

                $dateCol = Schema::hasColumn('correction_requests', 'work_date') ? 'work_date'
                    : (Schema::hasColumn('correction_requests', 'target_date') ? 'target_date'
                        : (Schema::hasColumn('correction_requests', 'date') ? 'date' : null));

                if (!$userCol || !$dateCol) {
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
                'user_id'   => $user->id,
                'name'      => $user->name,
                'clock_in'  => $attendanceDay->clock_in_at
                    ? Carbon::parse($attendanceDay->clock_in_at, $tz)->format('H:i') : '',
                'clock_out' => $attendanceDay->clock_out_at
                    ? Carbon::parse($attendanceDay->clock_out_at, $tz)->format('H:i') : '',
                'note'      => $attendanceDay->note,
                'breaks'    => $breaks,
            ],
            'attendanceDay' => $attendanceDay,
            'isLocked'      => $isLocked,
        ]);
    }


    // 編集フォーム（user+date ベース）
    public function updateByUserDate(AttendanceUpdateRequest $request, User $user)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $dateStr = $request->input('date') ?: $request->query('date');
        $date = $dateStr
            ? Carbon::parse($dateStr, $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            []
        );

        // ---- 入力を取り出し（HH:MM → Carbon。※ここではバリデーションしない！）----
        $toDT = function (?string $hm) use ($date, $tz) {
            return $hm ? Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $hm, $tz)->second(0) : null;
        };
        $in  = $toDT($request->input('clock_in'));
        $out = $toDT($request->input('clock_out'));

        // breaks[] 形式 or break1_*/break2_* 形式のどちらでも受ける
        $norm = [];
        $rawBreaks = (array) $request->input('breaks', []);
        if (!empty($rawBreaks)) {
            foreach ($rawBreaks as $row) {
                $s = $row['start'] ?? null;
                $e = $row['end']   ?? null;
                if ($s === null && $e === null) continue;
                $sd = $toDT($s);
                $ed = $toDT($e);
                if ($sd && $ed) $norm[] = [$sd, $ed];
            }
        } else {
            // prepareForValidation で写し替え済みの break1_*/break2_* を拾う
            $pairs = [
                [$request->input('break1_start'), $request->input('break1_end')],
                [$request->input('break2_start'), $request->input('break2_end')],
            ];
            foreach ($pairs as [$s, $e]) {
                if (!$s && !$e) continue;
                $sd = $toDT($s);
                $ed = $toDT($e);
                if ($sd && $ed) $norm[] = [$sd, $ed];
            }
        }

        DB::transaction(function () use ($attendanceDay, $in, $out, $request, $tz, $date, $user, $norm) {
            // 変更前スナップショット
            $attendanceDay->load('breakPeriods');
            $tbl = 'break_periods';
            $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
                : (Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                    : (Schema::hasColumn($tbl, 'start_at') ? 'start_at' : null));
            $colEnd   = Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
                : (Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                    : (Schema::hasColumn($tbl, 'end_at') ? 'end_at' : null));

            $before = [
                'clock_in_at'  => $attendanceDay->clock_in_at,
                'clock_out_at' => $attendanceDay->clock_out_at,
                'breaks'       => $attendanceDay->breakPeriods?->map(function ($b) use ($colStart, $colEnd) {
                    return [
                        'start' => $colStart ? ($b->{$colStart} ?? null) : null,
                        'end'   => $colEnd   ? ($b->{$colEnd}   ?? null) : null,
                    ];
                })->values()->all() ?? [],
            ];

            // 本体更新
            $attendanceDay->clock_in_at  = $in;
            $attendanceDay->clock_out_at = $out;
            $attendanceDay->note         = (string)$request->input('note', '');
            $attendanceDay->save();

            // 休憩差し替え
            if (method_exists($attendanceDay, 'breakPeriods')) {
                $attendanceDay->breakPeriods()->delete();
                if ($colStart && $colEnd) {
                    foreach ($norm as [$s, $e]) {
                        $attendanceDay->breakPeriods()->create([
                            $colStart => $s->copy(),
                            $colEnd   => $e->copy(),
                        ]);
                    }
                }
            }

            // 合計再計算
            $attendanceDay->load('breakPeriods');
            $breakSeconds = 0;
            foreach ($attendanceDay->breakPeriods as $bp) {
                $s = ($colStart && $bp->{$colStart}) ? Carbon::parse($bp->{$colStart}, $tz) : null;
                $e = ($colEnd   && $bp->{$colEnd})   ? Carbon::parse($bp->{$colEnd},   $tz) : null;
                if ($s && $e && $e->gt($s)) $breakSeconds += $e->diffInSeconds($s);
            }

            $adjustedOut = $out ? $out->copy() : null;
            if ($in && $adjustedOut && $adjustedOut->lt($in)) {
                $adjustedOut->addDay(); // 跨日ケア（必要に応じて）
            }
            $workSeconds = ($in && $adjustedOut) ? max(0, $adjustedOut->diffInSeconds($in) - $breakSeconds) : 0;

            if ($attendanceDay->isFillable('total_break_minutes')) {
                $attendanceDay->total_break_minutes = intdiv($breakSeconds, 60);
            }
            if ($attendanceDay->isFillable('total_work_minutes')) {
                $attendanceDay->total_work_minutes  = intdiv($workSeconds, 60);
            }
            if ($attendanceDay->isDirty()) $attendanceDay->save();

            // 監査ログ（存在すれば）
            if (class_exists(\App\Models\CorrectionLog::class) && Schema::hasTable('correction_logs')) {
                $after = [
                    'clock_in_at'  => $attendanceDay->clock_in_at,
                    'clock_out_at' => $attendanceDay->clock_out_at,
                    'breaks'       => $attendanceDay->breakPeriods?->map(function ($b) use ($colStart, $colEnd) {
                        return [
                            'start' => $colStart ? ($b->{$colStart} ?? null) : null,
                            'end'   => $colEnd   ? ($b->{$colEnd}   ?? null) : null,
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
                    $payload['correction_request_id'] = null;
                }
                try {
                    \App\Models\CorrectionLog::create($payload);
                } catch (\Throwable $e) {
                    // noop
                }
            }
        });

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
        // 1) 空の一覧を作る
        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = [
                'date'        => $d->copy(),
                'clock_in'    => null,
                'clock_out'   => null,
                'break_total' => null,
                'work_total'  => null,
            ];
        }


        // 2) 月内の実データを取得（★ work_date で絞る）
        $records = AttendanceDay::query()
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
                // 範囲外安全策（範囲外の日付は無視）
                continue;
            }

            $days[$key]['clock_in']  = optional($ad->clock_in_at)->format('H:i');
            $days[$key]['clock_out'] = optional($ad->clock_out_at)->format('H:i');

            // ★ モデルのアクセサを利用して「表示用 HH:MM」を取得する
            //    - break_total: 休憩合計（m>0 のときだけ 'HH:MM'、それ以外は null）
            //    - work_total : 勤務合計（0時跨ぎ＋休憩控除済み 'HH:MM'／未打刻は null）
            $days[$key]['break_total'] = $ad->break_total;  // 例: '01:30' / null
            $days[$key]['work_total']  = $ad->work_total;   // 例: '08:00' / null
        }


        // ビューへ
        return view('admin.attendance.staff', [
            'user' => $user,
            'month' => $month,
            'days'  => collect($days)->values(), // 並びは 1日→末日
        ]);
    }


    public function staffCsv(Request $request, int $id): StreamedResponse
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
        if (!class_exists(CorrectionRequest::class)) return false;
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
