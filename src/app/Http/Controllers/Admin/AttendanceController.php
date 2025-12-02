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
    /**
     * 日別勤怠一覧（admin.attendance.list）
     * - ★ 承認前（pending）の修正申請がある場合、一覧表示に反映する
     */
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        // ▼ 1) 表示日決定
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

        // ▼ 2) 履歴ペイン用のページネーション（既存のまま）
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

        // ▼ 3) 当日の勤怠（user_id キー）
        $attByUser = AttendanceDay::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereDate('work_date', $date->toDateString())
            ->with('breakPeriods')
            ->get()
            ->keyBy('user_id');

        // ▼ 4) 一覧対象ユーザー
        $users = User::query()
            ->when($userId, fn($q) => $q->where('id', $userId))
            ->when(Schema::hasColumn('users', 'role'), fn($q) => $q->where('role', 'user'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // ▼ 4.5) 当日 pending 修正（最新1件/ユーザー）
        $pendingByUser = collect();
        if (class_exists(CorrectionRequest::class) && Schema::hasTable('correction_requests')) {
            $tbl = 'correction_requests';
            $userCol = Schema::hasColumn($tbl, 'requested_user_id') ? 'requested_user_id'
                : (Schema::hasColumn($tbl, 'requested_by') ? 'requested_by'
                    : (Schema::hasColumn($tbl, 'user_id') ? 'user_id' : null));
            $dateCol = Schema::hasColumn($tbl, 'work_date') ? 'work_date'
                : (Schema::hasColumn($tbl, 'target_date') ? 'target_date'
                    : (Schema::hasColumn($tbl, 'date') ? 'date' : null));

            if ($userCol && $dateCol && Schema::hasColumn($tbl, 'status')) {
                $pendings = CorrectionRequest::where('status', 'pending')
                    ->whereDate($dateCol, $date->toDateString())
                    ->orderByDesc('id')
                    ->get();
                foreach ($pendings as $cr) {
                    $uid = $cr->{$userCol} ?? null;
                    if ($uid && !$pendingByUser->has($uid)) {
                        $pendingByUser->put($uid, $cr);
                    }
                }
            }
        }

        // ▼ 小道具
        $toHM = function ($v) use ($tz) {
            if (!$v) return null;
            if (is_string($v) && preg_match('/^\d{1,2}:\d{2}$/', $v)) return $v;
            try {
                return Carbon::parse($v, $tz)->format('H:i');
            } catch (\Throwable $e) {
                return null;
            }
        };
        $toMin = function ($v) use ($tz) {
            if (!$v) return null;
            if ($v instanceof \DateTimeInterface) return (int)$v->format('H') * 60 + (int)$v->format('i');
            if (is_string($v) && preg_match('/^\d{1,2}:\d{2}$/', $v)) {
                [$h, $m] = explode(':', $v);
                return (int)$h * 60 + (int)$m;
            }
            try {
                $c = Carbon::parse($v, $tz);
                return $c->hour * 60 + $c->minute;
            } catch (\Throwable $e) {
                return null;
            }
        };
        $minutesToHMM = fn($m) => $m === null ? null : sprintf('%d:%02d', intdiv((int)$m, 60), (int)$m % 60);

        // ▼ 5) 表示用レコード生成（★pending上書き＋合計再計算）
        $records = $users->map(function ($u) use ($attByUser, $pendingByUser, $toHM, $toMin, $minutesToHMM) {
            $a = $attByUser->get($u->id);

            // Attendance 生値
            $clockInHM  = $a?->clock_in_at  ? $a->clock_in_at->format('H:i')  : null;
            $clockOutHM = $a?->clock_out_at ? $a->clock_out_at->format('H:i') : null;

            // 既定の break 合計（必要なら後で再計算）
            $breakMinBase = $a->total_break_minutes ?? $a->break_minutes ?? null;

            // -------- Pending 反映 --------
            $isPending = false;
            $p = $pendingByUser->get($u->id);
            $payloadBreaks = null;

            if ($p) {
                $isPending = true;
                if (!empty($p->payload)) {
                    try {
                        $pl = is_array($p->payload) ? $p->payload : json_decode($p->payload, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        $pl = [];
                    }
                    // clock_in/out
                    if (array_key_exists('clock_in', $pl))  $clockInHM  = $toHM($pl['clock_in'])  ?? $clockInHM;
                    if (array_key_exists('clock_out', $pl)) $clockOutHM = $toHM($pl['clock_out']) ?? $clockOutHM;

                    // breaks（一覧でも反映して合計再計算）
                    if (isset($pl['breaks']) && is_array($pl['breaks'])) {
                        $payloadBreaks = [];
                        foreach ($pl['breaks'] as $b) {
                            $s = $toHM($b['start'] ?? null);
                            $e = $toHM($b['end']   ?? null);
                            if ($s && $e) $payloadBreaks[] = [$s, $e];
                        }
                    }
                } else {
                    // payload 無し → proposed_* だけ反映
                    if (!empty($p->proposed_clock_in_at))  $clockInHM  = $toHM($p->proposed_clock_in_at)  ?? $clockInHM;
                    if (!empty($p->proposed_clock_out_at)) $clockOutHM = $toHM($p->proposed_clock_out_at) ?? $clockOutHM;
                    // 休憩は proposed_* では持ってこられないので据え置き
                }
            }

            // break 合計の決定：payload の breaks を最優先 → なければモデル既存 → 明細から再計算
            if (is_array($payloadBreaks)) {
                $sum = 0;
                foreach ($payloadBreaks as [$s, $e]) {
                    $sm = $toMin($s);
                    $em = $toMin($e);
                    if ($sm !== null && $em !== null) {
                        if ($em < $sm) $em += 24 * 60;
                        $sum += max(0, $em - $sm);
                    }
                }
                $breakMin = $sum;
            } else {
                // モデル値が null/0 かつ明細があれば再計算
                $breakMin = $breakMinBase;
                $needRecalc = ($breakMin === null) || ($breakMin == 0 && $a && $a->relationLoaded('breakPeriods') && $a->breakPeriods->count() > 0);
                if ($a && $needRecalc) {
                    $tbl = 'break_periods';
                    $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
                        : (Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                            : (Schema::hasColumn($tbl, 'start_at') ? 'start_at' : null));
                    $colEnd = Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
                        : (Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                            : (Schema::hasColumn($tbl, 'end_at') ? 'end_at' : null));
                    $sum = 0;
                    if ($colStart && $colEnd) {
                        foreach ($a->breakPeriods as $bp) {
                            $sm = $toMin($bp->{$colStart} ?? null);
                            $em = $toMin($bp->{$colEnd}   ?? null);
                            if ($sm !== null && $em !== null) {
                                if ($em < $sm) $em += 24 * 60;
                                $sum += max(0, $em - $sm);
                            }
                        }
                    }
                    $breakMin = $sum;
                }
            }

            // 勤務合計は「pending 反映後」の in/out と breakMin で再計算
            // ▼ in/out を分に
            $inMin  = $toMin($clockInHM);
            $outMin = $toMin($clockOutHM);

            // 跨日ケア込みの“勤務スパン”を先に出す
            $spanMin = null;
            if ($inMin !== null && $outMin !== null) {
                $spanMin = $outMin - $inMin;
                if ($spanMin < 0) $spanMin += 24 * 60; // 退勤が翌日のとき
            }

            // ...（ここまで今まで通りでOK）...

            // ここまでで $breakMin が決まっているはず
            // ★ 追加：休憩合計は勤務スパンを超えないようにする
            if ($spanMin !== null) {
                $breakMin = min((int)($breakMin ?? 0), $spanMin);
            }

            // ▼ 勤務合計（休憩控除後）
            $workMin = null;
            if ($spanMin !== null) {
                $workMin = max(0, $spanMin - (int)($breakMin ?? 0));
            }

            // 表示用 record
            $hasPunch = ($clockInHM || $clockOutHM);
            return [
                'id'           => $a->id ?? null,
                'user_id'      => $u->id,
                'name'         => $u->name,
                'clock_in'     => $clockInHM,
                'clock_out'    => $clockOutHM,
                'break_total'  => $hasPunch ? $minutesToHMM($breakMin ?? 0) : null,
                'work_total'   => is_null($workMin) ? null : $minutesToHMM($workMin),
                'is_pending'   => $isPending, // ← Blade でバッジ表示に使える
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
        $tz = config('app.timezone', 'Asia/Tokyo');
        $dateParam = $request->query('date') ?? $attendanceDay->work_date;
        $date = $dateParam ? Carbon::parse($dateParam, $tz)->startOfDay() : Carbon::now($tz)->startOfDay();

        return redirect()->route('admin.attendance.detail', [
            'id'   => $attendanceDay->user_id,
            'date' => $date->toDateString(),
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

        // ---- リレーション／列名の自動判定 ----
        $relName = method_exists($attendanceDay, 'breakPeriods') ? 'breakPeriods'
            : (method_exists($attendanceDay, 'breaks') ? 'breaks' : null);

        if ($relName) $attendanceDay->load($relName);

        $tbl = 'break_periods';
        if (
            $relName
            && $attendanceDay->$relName instanceof \Illuminate\Database\Eloquent\Collection
            && $attendanceDay->$relName->first()
        ) {
            $tbl = $attendanceDay->$relName->first()->getTable();
        } elseif (Schema::hasTable('breaks')) {
            $tbl = 'breaks';
        }

        $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
            : (Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                : (Schema::hasColumn($tbl, 'start_at')   ? 'start_at'   : null));
        $colEnd   = Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
            : (Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                : (Schema::hasColumn($tbl, 'end_at')   ? 'end_at'   : null));

        // --- record を作る ---
        $record = [
            'user_id'   => $user->id,
            'name'      => $user->name,
            'clock_in'  => $attendanceDay->clock_in_at  ? $attendanceDay->clock_in_at->format('H:i')  : '',
            'clock_out' => $attendanceDay->clock_out_at ? $attendanceDay->clock_out_at->format('H:i') : '',
            'note'      => $attendanceDay->note,
            'breaks'    => [],
        ];

        if ($relName && $colStart && $colEnd) {
            foreach ($attendanceDay->$relName as $bp) {
                $record['breaks'][] = [
                    'start' => $bp->{$colStart} ? Carbon::parse($bp->{$colStart}, $tz)->format('H:i') : '',
                    'end'   => $bp->{$colEnd}   ? Carbon::parse($bp->{$colEnd},   $tz)->format('H:i') : '',
                ];
            }
        }
        // 常に入力1行を追加
        $record['breaks'][] = ['start' => '', 'end' => ''];

        // ---- pending で上書き ----
        $pending = CorrectionRequest::where('attendance_day_id', $attendanceDay->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pending) {
            if (!empty($pending->payload)) {
                $p  = is_array($pending->payload) ? $pending->payload : json_decode($pending->payload, true);
                $hm = function ($v) use ($tz) {
                    if (is_string($v) && preg_match('/^\d{1,2}:\d{2}$/', $v)) return $v;
                    return $v ? Carbon::parse($v, $tz)->format('H:i') : null;
                };
                if (array_key_exists('clock_in',  $p)) $record['clock_in']  = $hm($p['clock_in'])  ?? '';
                if (array_key_exists('clock_out', $p)) $record['clock_out'] = $hm($p['clock_out']) ?? '';
                if (array_key_exists('note',      $p)) $record['note']      = (string)($p['note'] ?? '');

                if (!empty($p['breaks']) && is_array($p['breaks'])) {
                    $record['breaks'] = [];
                    foreach ($p['breaks'] as $b) {
                        $record['breaks'][] = [
                            'start' => $hm($b['start'] ?? null) ?? '',
                            'end'   => $hm($b['end']   ?? null) ?? '',
                        ];
                    }
                    $record['breaks'][] = ['start' => '', 'end' => ''];
                }
            } else {
                $record['clock_in']  = $pending->proposed_clock_in_at
                    ? Carbon::parse($pending->proposed_clock_in_at, $tz)->format('H:i')
                    : $record['clock_in'];
                $record['clock_out'] = $pending->proposed_clock_out_at
                    ? Carbon::parse($pending->proposed_clock_out_at, $tz)->format('H:i')
                    : $record['clock_out'];
                $record['note']      = $pending->proposed_note ?? $record['note'];
            }
        }

        $isPending  = (bool)$pending;

        // ... 承認状況の取得後
        $approved = CorrectionRequest::where('attendance_day_id', $attendanceDay->id)
            ->where('status', 'approved')
            ->latest('id')
            ->first();

        $mode = $isPending ? 'pending' : ($approved ? 'approved' : 'normal');
        $isEditable = ($mode === 'normal');

        return view('admin.attendance.detail', [
            'user'          => $user,
            'date'          => $date,
            'record'        => $record,        // ← 定義済みだから安全
            'attendanceDay' => $attendanceDay,
            'isPending'     => $isPending,
            'mode'          => $mode,
            'isEditable'    => $isEditable,
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

        if ($this->hasPendingCorrection($user->id, $date) || $this->hasApprovedCorrection($user->id, $date)) {
            return back()->withInput()->with('error', '承認済み／承認待ちのため修正はできません。');
        }

        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            []
        );

        // ---- 休憩リレーション＆列名の自動判定 ----
        $relName = method_exists($attendanceDay, 'breakPeriods') ? 'breakPeriods'
            : (method_exists($attendanceDay, 'breaks') ? 'breaks' : null);

        $tbl = 'break_periods';
        if ($relName) {
            $attendanceDay->load($relName);
            if ($attendanceDay->$relName->first()) {
                $tbl = $attendanceDay->$relName->first()->getTable();
            } elseif (Schema::hasTable('breaks')) {
                $tbl = 'breaks';
            }
        } elseif (Schema::hasTable('breaks')) {
            $tbl = 'breaks';
        }

        $colStart = Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
            : (Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                : (Schema::hasColumn($tbl, 'start_at') ? 'start_at' : null));
        $colEnd   = Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
            : (Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                : (Schema::hasColumn($tbl, 'end_at') ? 'end_at' : null));

        // ---- 入力(HH:MM) -> Carbon 変換 ----
        $toDT = function (?string $hm) use ($date, $tz) {
            return ($hm && strpos($hm, ':') !== false)
                ? Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $hm, $tz)->second(0)
                : null;
        };
        $in  = $toDT($request->input('clock_in'));
        $out = $toDT($request->input('clock_out'));

        // breaks[] 形式 or break1_*/break2_* 形式のどちらでも受ける（空行は除外）
        $norm = [];
        $rawBreaks = (array) $request->input('breaks', []);
        if ($rawBreaks) {
            foreach ($rawBreaks as $row) {
                $s = $row['start'] ?? null;
                $e = $row['end']   ?? null;
                if ($s === null && $e === null) continue;
                $sd = $toDT($s);
                $ed = $toDT($e);
                if ($sd && $ed) $norm[] = [$sd, $ed];
            }
        } else {
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

        DB::transaction(function () use ($attendanceDay, $in, $out, $request, $tz, $date, $user, $norm, $relName, $colStart, $colEnd) {
            // 変更前スナップショット
            if ($relName) $attendanceDay->load($relName);

            $before = [
                'clock_in_at'  => $attendanceDay->clock_in_at,
                'clock_out_at' => $attendanceDay->clock_out_at,
                'breaks'       => ($relName && $colStart && $colEnd)
                    ? $attendanceDay->$relName?->map(function ($b) use ($colStart, $colEnd) {
                        return ['start' => $b->{$colStart} ?? null, 'end' => $b->{$colEnd} ?? null];
                    })->values()->all()
                    : [],
            ];

            // 本体更新
            $attendanceDay->clock_in_at  = $in;
            $attendanceDay->clock_out_at = $out;
            $attendanceDay->note         = (string)$request->input('note', '');
            $attendanceDay->save();

            // 休憩差し替え（breaks でも breakPeriods でもOK）
            if ($relName && $colStart && $colEnd) {
                // 既存削除
                $attendanceDay->$relName()->delete();
                // 再作成
                foreach ($norm as [$s, $e]) {
                    $attendanceDay->$relName()->create([
                        $colStart => $s->copy(),
                        $colEnd   => $e->copy(),
                    ]);
                }
            }

            // 合計再計算
            if ($relName) $attendanceDay->load($relName);
            $breakSeconds = 0;
            if ($relName && $colStart && $colEnd) {
                foreach ($attendanceDay->$relName as $bp) {
                    $s = $bp->{$colStart} ? Carbon::parse($bp->{$colStart}, $tz) : null;
                    $e = $bp->{$colEnd}   ? Carbon::parse($bp->{$colEnd},   $tz) : null;
                    if ($s && $e && $e->gt($s)) $breakSeconds += $e->diffInSeconds($s);
                }
            }

            $adjustedOut = $out ? $out->copy() : null;
            if ($in && $adjustedOut && $adjustedOut->lt($in)) $adjustedOut->addDay(); // 跨日ケア

            $workSeconds = ($in && $adjustedOut) ? max(0, $adjustedOut->diffInSeconds($in) - $breakSeconds) : 0;

            if ($attendanceDay->isFillable('total_break_minutes')) {
                $attendanceDay->total_break_minutes = intdiv($breakSeconds, 60);
            }
            if ($attendanceDay->isFillable('total_work_minutes')) {
                $attendanceDay->total_work_minutes  = intdiv($workSeconds, 60);
            }
            if ($attendanceDay->isDirty()) $attendanceDay->save();

            // 監査ログ（存在時のみ）
            if (class_exists(\App\Models\CorrectionLog::class) && Schema::hasTable('correction_logs')) {
                $after = [
                    'clock_in_at'  => $attendanceDay->clock_in_at,
                    'clock_out_at' => $attendanceDay->clock_out_at,
                    'breaks'       => ($relName && $colStart && $colEnd)
                        ? $attendanceDay->$relName?->map(function ($b) use ($colStart, $colEnd) {
                            return ['start' => $b->{$colStart} ?? null, 'end' => $b->{$colEnd} ?? null];
                        })->values()->all()
                        : [],
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
                } catch (\Throwable $e) { /* noop */
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

        // 2) 月内の実データを取得
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
            if (!isset($days[$key])) continue;

            $days[$key]['clock_in']  = optional($ad->clock_in_at)->format('H:i');
            $days[$key]['clock_out'] = optional($ad->clock_out_at)->format('H:i');

            // モデルのアクセサを利用して「表示用 HH:MM」を取得する想定
            $days[$key]['break_total'] = $ad->break_total;  // 例: '01:30' / null
            $days[$key]['work_total']  = $ad->work_total;   // 例: '08:00' / null
        }

        return view('admin.attendance.staff', [
            'user'  => $user,
            'month' => $month,
            'days'  => collect($days)->values(),
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

        $days = AttendanceDay::where('user_id', $id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $month->format('Y-m'));

        return response()->streamDownload(function () use ($days) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'clock_in', 'clock_out', 'break_total', 'work_total']);

            foreach ($days as $d) {
                $date        = $d->work_date;
                $clockIn     = optional($d->clock_in_at)->format('H:i');
                $clockOut    = optional($d->clock_out_at)->format('H:i');
                $breakTotal  = $d->break_total_text ?? '';
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
        if (!class_exists(CorrectionRequest::class)) return false;
        if (!Schema::hasTable('correction_requests')) return false;

        $tbl = 'correction_requests';

        $userCol = Schema::hasColumn($tbl, 'requested_user_id') ? 'requested_user_id'
            : (Schema::hasColumn($tbl, 'requested_by') ? 'requested_by'
                : (Schema::hasColumn($tbl, 'user_id') ? 'user_id' : null));

        $dateCol = Schema::hasColumn($tbl, 'work_date') ? 'work_date'
            : (Schema::hasColumn($tbl, 'target_date') ? 'target_date'
                : (Schema::hasColumn($tbl, 'date') ? 'date' : null));

        if (!$userCol || !$dateCol || !Schema::hasColumn($tbl, 'status')) {
            return false;
        }

        return CorrectionRequest::where($userCol, $userId)
            ->whereDate($dateCol, $date->toDateString())
            ->where('status', 'pending')
            ->exists();
    }

    private function hasApprovedCorrection(int $userId, Carbon $date): bool
    {
        if (!class_exists(CorrectionRequest::class)) return false;
        if (!Schema::hasTable('correction_requests')) return false;

        $tbl = 'correction_requests';
        $userCol = Schema::hasColumn($tbl, 'requested_user_id') ? 'requested_user_id'
            : (Schema::hasColumn($tbl, 'requested_by') ? 'requested_by'
                : (Schema::hasColumn($tbl, 'user_id') ? 'user_id' : null));
        $dateCol = Schema::hasColumn($tbl, 'work_date') ? 'work_date'
            : (Schema::hasColumn($tbl, 'target_date') ? 'target_date'
                : (Schema::hasColumn($tbl, 'date') ? 'date' : null));
        if (!$userCol || !$dateCol || !Schema::hasColumn($tbl, 'status')) return false;

        return CorrectionRequest::where($userCol, $userId)
            ->whereDate($dateCol, $date->toDateString())
            ->where('status', 'approved')
            ->exists();
    }
}
