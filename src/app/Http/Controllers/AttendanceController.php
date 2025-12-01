<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\AttendanceDetailRequest;
use App\Models\AttendanceDay;
use App\Models\CorrectionRequest;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /** 打刻画面（GET /attendance/stamp） */
    public function stamp(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz   = config('app.timezone', 'Asia/Tokyo');
        $now  = \Carbon\Carbon::now($tz)->second(0);
        $todayStr = $now->toDateString();

        // 1) 未退勤のオープン勤務（跨日も含め最優先）
        $open = \App\Models\AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        // 2) 今日のレコード
        $today = \App\Models\AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $todayStr)
            ->first();

        $state = 'before';
        $displayTime = $now->format('H:i');

        $applyState = function ($day) use ($tz, &$state, &$displayTime) {
            if (!$day) return;
            if ($day->clock_out_at) {
                $state = 'after';
                // 退勤済みのときは現在時刻を表示（またはお好みで退勤時刻でもOK）
                $displayTime = \Carbon\Carbon::now($tz)->format('H:i');
                return;
            }
            $hasOpenBreak = $day->breaks()->whereNull('ended_at')->exists();
            if ($hasOpenBreak) {
                $state = 'break';
                $displayTime = \Carbon\Carbon::now($tz)->format('H:i');
                return;
            }
            if ($day->clock_in_at) {
                $state = 'working';
                // 出勤中は出勤時刻を表示
                $displayTime = \Carbon\Carbon::parse($day->clock_in_at, $tz)->format('H:i');
                return;
            }
            $state = 'before';
            $displayTime = \Carbon\Carbon::now($tz)->format('H:i');
        };

        if ($open) {
            // 跨日勤務など、未退勤があるときはそれをそのまま表示
            $applyState($open);
        } elseif ($today) {
            // きょうのレコードがあるときだけ、その状態を採用
            $applyState($today);
        } else {
            // きょうのレコードが無ければ「before」で新規出勤できる
            $state = 'before';
            $displayTime = $now->format('H:i');
        }

        return view('attendance.stamp', [
            'state'       => $state,
            'today'       => $now,
            'displayTime' => $displayTime,
        ]);
    }


    /** 出勤 */
    public function clockIn()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $now = Carbon::now(config('app.timezone', 'Asia/Tokyo'));
        $day = AttendanceDay::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $now->toDateString()],
            ['status'  => 'before']
        );

        if (!$day->clock_in_at) {
            $day->clock_in_at = $now;
            $day->status = 'working';
            $day->save();
        }
        return back()->with('status', 'clocked-in');
    }

    /** 退勤 */
    public function clockOut()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz);

        // 未退勤を最優先で取得（当日 or 直近）
        $day = AttendanceDay::where('user_id', $userId)
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        // 未退勤がなければ今日のレコード
        if (!$day) {
            $day = AttendanceDay::where('user_id', $userId)
                ->whereDate('work_date', $now->toDateString())
                ->first();
        }

        if (!$day || !$day->clock_in_at) {
            return back()->with('error', '退勤できる勤務がありません');
        }

        // 開いている休憩があれば閉じる
        if ($openBreak = $day->breaks()->whereNull('ended_at')->first()) {
            $openBreak->ended_at = $now;
            $openBreak->save();
        }

        $day->clock_out_at = $now;

        // 休憩合計（未計算なら明細から）
        $breakMin = (int)($day->total_break_minutes ?? 0);
        if ($breakMin === 0) {
            $breakMin = 0;
            foreach ($day->breaks as $bp) {
                if ($bp->started_at && $bp->ended_at) {
                    $breakMin += Carbon::parse($bp->ended_at, $tz)
                        ->diffInMinutes(Carbon::parse($bp->started_at, $tz));
                }
            }
            $day->total_break_minutes = $breakMin;
        }

        // 実働
        if ($day->clock_in_at && $day->clock_out_at) {
            $worked = Carbon::parse($day->clock_out_at, $tz)
                ->diffInMinutes(Carbon::parse($day->clock_in_at, $tz), false) - $breakMin;
            $day->total_work_minutes = max(0, $worked);
        }

        $day->status = 'after'; // ← 列挙に合わせる
        $day->save();

        return back()->with('status', 'clocked-out');
    }

    /** 休憩入り */
    public function breakIn()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz);

        $day = AttendanceDay::where('user_id', $userId)
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        if (!$day || !$day->clock_in_at) {
            return back()->with('error', '休憩開始できる勤務がありません');
        }

        if (!$day->breaks()->whereNull('ended_at')->exists()) {
            $day->breaks()->create(['started_at' => $now]);
            $day->status = 'break'; // ← 列挙に合わせる
            $day->save();
        }

        return back()->with('status', 'break-started');
    }

    /** 休憩戻り */
    public function breakOut()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz);

        $day = AttendanceDay::where('user_id', $userId)
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        if (!$day) return back()->with('error', '対象勤務がありません');

        if ($openBreak = $day->breaks()->whereNull('ended_at')->first()) {
            $openBreak->ended_at = $now;
            $openBreak->save();
        }

        $day->status = 'working';
        $day->save();

        return back()->with('status', 'break-ended');
    }

    /** 多機能打刻（フォーム "action"） */
    public function punch(Request $request)
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $validated = $request->validate([
            'action' => 'required|in:clock-in,break-start,break-end,clock-out',
        ]);
        $action = $validated['action'];

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz)->second(0);

        $day = AttendanceDay::with('breaks')->firstOrCreate(
            ['user_id' => $userId, 'work_date' => $now->toDateString()],
            ['status'  => 'before']
        );

        switch ($action) {
            case 'clock-in':
                if (!$day->clock_in_at) {
                    $day->clock_in_at = $now;
                    $day->status = 'working';
                    $day->save();
                }
                return back()->with('status', 'clock-in');

            case 'break-start':
                if (!$day->breaks()->whereNull('ended_at')->exists()) {
                    $day->breaks()->create(['started_at' => $now]);
                    $day->status = 'break';
                    $day->save();
                }
                return back()->with('status', 'break-start');

            case 'break-end':
                if ($open = $day->breaks()->whereNull('ended_at')->latest('started_at')->first()) {
                    $open->ended_at = $now;
                    $open->save();
                    $day->status = 'working';
                    $day->save();
                }
                return back()->with('status', 'break-end');

            case 'clock-out':
                if (!$day->clock_out_at) {
                    $day->clock_out_at = $now;
                }

                $breakMin = (int)($day->total_break_minutes ?? 0);
                if ($breakMin === 0) {
                    foreach ($day->breaks as $bp) {
                        if ($bp->started_at && $bp->ended_at) {
                            $breakMin += Carbon::parse($bp->ended_at, $tz)
                                ->diffInMinutes(Carbon::parse($bp->started_at, $tz));
                        }
                    }
                    $day->total_break_minutes = $breakMin;
                }

                if ($day->clock_in_at && $day->clock_out_at) {
                    $worked = Carbon::parse($day->clock_out_at, $tz)
                        ->diffInMinutes(Carbon::parse($day->clock_in_at, $tz), false) - $breakMin;
                    $day->total_work_minutes = max(0, $worked);
                }

                $day->status = 'after';
                $day->save();

                return back()->with('status', 'clock-out');
        }
    }

    /** 勤怠一覧（?month=YYYY-MM） */
    public function list(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz = config('app.timezone', 'Asia/Tokyo');

        $monthParam = $request->query('month');
        if ($monthParam) {
            try {
                $month = Carbon::createFromFormat('Y-m', $monthParam, $tz)->startOfMonth();
            } catch (\Throwable $e) {
                $month = Carbon::now($tz)->startOfMonth();
            }
        } else {
            $month = Carbon::now($tz)->startOfMonth();
        }

        $start = $month->copy();
        $end   = $month->copy()->endOfMonth();

        $rows = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($d) => \Carbon\Carbon::parse($d->work_date, $tz)->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $ad  = $rows[$key] ?? null;

            // 休憩の明細テキスト（合計が空のときのフォールバック用）
            $breakText = '';
            if ($ad && $ad->breaks->isNotEmpty()) {
                $parts = [];
                foreach ($ad->breaks as $b) {
                    $s = $b->started_at ? Carbon::parse($b->started_at, $tz)->format('H:i') : null;
                    $e = $b->ended_at   ? Carbon::parse($b->ended_at,   $tz)->format('H:i') : null;
                    if ($s || $e) $parts[] = trim(($s ?? '') . ($e ? "-$e" : ''), '-');
                }
                $breakText = implode(', ', array_filter($parts));
            }

            // 休憩合計（total_break_minutes が無ければ明細から集計）
            $breakMin = 0;
            if ($ad) {
                $breakMin = (int)($ad->total_break_minutes ?? 0);
                if ($breakMin === 0 && $ad->breaks) {
                    foreach ($ad->breaks as $bp) {
                        if ($bp->started_at && $bp->ended_at) {
                            $bs = Carbon::parse($bp->started_at, $tz)->second(0);
                            $be = Carbon::parse($bp->ended_at,   $tz)->second(0);
                            // マイナス防止（順序が逆だった場合も0に）
                            $breakMin += max(0, $be->diffInMinutes($bs, true));
                        }
                    }
                }
            } // ★ ←←← ここが不足していました（if ($ad) のクローズ）

            // 勤務合計（勤務スパンから休憩を控除）
            $workMin = null;
            if ($ad && $ad->clock_in_at && $ad->clock_out_at) {
                $in  = Carbon::parse($ad->clock_in_at,  $tz)->second(0);
                $out = Carbon::parse($ad->clock_out_at, $tz)->second(0);
                $spanMin = $out->diffInMinutes($in, true);
                $effectiveBreak = min($breakMin, $spanMin);
                $workMin = max(0, $spanMin - $effectiveBreak);
            }

            $days[] = [
                'date'        => $d->toDateString(),
                'clock_in'    => $ad && $ad->clock_in_at  ? Carbon::parse($ad->clock_in_at,  $tz)->format('H:i') : '',
                'clock_out'   => $ad && $ad->clock_out_at ? Carbon::parse($ad->clock_out_at, $tz)->format('H:i') : '',
                'break_total' => $breakMin > 0 ? $this->formatMinutes($breakMin) : '',
                'break_text'  => $breakText, // 合計が空の時だけBladeで使用
                'work_total'  => $workMin !== null ? $this->formatMinutes($workMin) : '',
            ];
        } // for ループ終わり

        $prevMonth = $month->copy()->subMonthNoOverflow();
        $nextMonth = $month->copy()->addMonthNoOverflow();

        return view('attendance.list', compact('month', 'days', 'prevMonth', 'nextMonth'));
    }


    private function formatMinutes(?int $min): string
    {
        if ($min === null) return '';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    private function getAttendanceDayFor(int $userId, Carbon $date): ?AttendanceDay
    {
        return AttendanceDay::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $date->toDateString()],
            []
        );
    }

    // 承認待ち判定
    private function hasPendingCorrection(int $userId, Carbon $date): bool
    {
        $day = AttendanceDay::where('user_id', $userId)
            ->whereDate('work_date', $date->toDateString())
            ->first();

        if (! $day) return false;

        return CorrectionRequest::where('attendance_day_id', $day->id)
            ->where('requested_by', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    /** 勤怠詳細（GET /attendance/detail?date=YYYY-MM-DD） */
    public function detail(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz = config('app.timezone', 'Asia/Tokyo');
        $dateStr = $request->query('date');
        if (!$dateStr) {
            return redirect()->route('attendance.list')
                ->with('error', '日付が指定されていません。');
        }

        try {
            $target = \Carbon\Carbon::createFromFormat('Y-m-d', $dateStr, $tz)
                ->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }

        // 元データ
        $day = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $target->toDateString())
            ->first();

        $record = [
            'user_id'   => $user->id,
            'name'      => $user->name,
            'clock_in'  => $day && $day->clock_in_at
                ? Carbon::parse($day->clock_in_at, $tz)->format('H:i')
                : '',
            'clock_out' => $day && $day->clock_out_at
                ? Carbon::parse($day->clock_out_at, $tz)->format('H:i')
                : '',
            'note'      => $day->note ?? '',
            'breaks'    => [],
        ];

        if ($day && $day->breaks) {
            foreach ($day->breaks as $b) {
                $record['breaks'][] = [
                    'start' => $b->started_at
                        ? Carbon::parse($b->started_at, $tz)->format('H:i')
                        : '',
                    'end'   => $b->ended_at
                        ? Carbon::parse($b->ended_at, $tz)->format('H:i')
                        : '',
                ];
            }
        }

        /* -------------------------------
     * ★ 修正申請の状態チェック
     * ------------------------------- */

        $mode = 'normal';   // pending | approved | normal
        $isPending = false;

        if ($day) {
            // 承認待ちの申請
            $pending = CorrectionRequest::where('attendance_day_id', $day->id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            // 承認済みの申請
            $approved = CorrectionRequest::where('attendance_day_id', $day->id)
                ->where('requested_by', $user->id)
                ->where('status', 'approved')
                ->latest('id')
                ->first();

            if ($pending) {
                $mode = 'pending';
                $isPending = true;

                // payload 優先で上書き
                if (!empty($pending->payload)) {
                    $p = is_array($pending->payload)
                        ? $pending->payload
                        : json_decode($pending->payload, true);

                    $hm = fn($v) => (is_string($v) && preg_match('/^\d{1,2}:\d{2}$/', $v))
                        ? $v
                        : ($v ? Carbon::parse($v, $tz)->format('H:i') : null);

                    if (!empty($p['clock_in']))  $record['clock_in']  = $hm($p['clock_in']);
                    if (!empty($p['clock_out'])) $record['clock_out'] = $hm($p['clock_out']);
                    if (array_key_exists('note', $p) && $p['note'] !== null) {
                        $record['note'] = (string)$p['note'];
                    }

                    if (!empty($p['breaks']) && is_array($p['breaks'])) {
                        $newBreaks = [];
                        foreach ($p['breaks'] as $b) {
                            $newBreaks[] = [
                                'start' => $hm($b['start'] ?? null) ?? '',
                                'end'   => $hm($b['end']   ?? null) ?? '',
                            ];
                        }
                        $record['breaks'] = $newBreaks;
                    }
                } else {
                    if ($pending->proposed_clock_in_at) {
                        $record['clock_in'] = Carbon::parse($pending->proposed_clock_in_at, $tz)->format('H:i');
                    }
                    if ($pending->proposed_clock_out_at) {
                        $record['clock_out'] = Carbon::parse($pending->proposed_clock_out_at, $tz)->format('H:i');
                    }
                    if ($pending->proposed_note !== null) {
                        $record['note'] = $pending->proposed_note;
                    }
                }
            } elseif ($approved) {
                $mode = 'approved';
            }
        }

        $isEditable = ($mode === 'normal');

        if (empty($record['breaks'])) {
            $record['breaks'] = [];
        }
        $record['breaks'][] = ['start' => '', 'end' => ''];

        return view('attendance.detail', [
            'date'       => $target,
            'record'     => $record,
            'user'       => $user,
            'isPending'  => $mode === 'pending',   // 既存Blade互換
            'isEditable' => $isEditable,           // 新：編集可否
            'mode'       => $mode,                 // 新：状態
        ]);
    }



    /**
     * 修正申請
     */
    public function requestCorrection(AttendanceDetailRequest $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $data  = $request->validated();
        $tz    = config('app.timezone', 'Asia/Tokyo');
        $date  = Carbon::parse($data['date'], $tz)->startOfDay();

        $day = $this->getAttendanceDayFor($user->id, $date);

        // 二重申請防止（pending があれば弾く）
        $already = CorrectionRequest::where('attendance_day_id', $day->id)
            ->where('requested_by', $user->id)
            ->where('status', 'pending')
            ->exists();
        if ($already) {
            return redirect()
                ->route('attendance.detail', ['date' => $date->toDateString()])
                ->with('status', 'すでに承認待ちの申請があります。');
        }

        // H:i → DateTime（当日付） ※休憩は H:i のまま payload に保存します
        $toDateTime = function (?string $hm) use ($date) {
            if ($hm === null || $hm === '' || !str_contains($hm, ':')) return null;
            [$h, $m] = explode(':', $hm);
            return $date->copy()->setTime((int)$h, (int)$m, 0);
        };

        // breaks を H:i のまま配列に整える（空行は除外）
        $payloadBreaks = [];
        foreach (($data['breaks'] ?? []) as $b) {
            $s = trim((string)($b['start'] ?? ''));
            $e = trim((string)($b['end'] ?? ''));
            if ($s !== '' || $e !== '') {
                $payloadBreaks[] = ['start' => $s, 'end' => $e];
            }
        }

        CorrectionRequest::create([
            'attendance_day_id'     => $day->id,
            'requested_by'          => $user->id,
            'reason'                => null,
            'proposed_clock_in_at'  => $toDateTime($data['clock_in']  ?? null),
            'proposed_clock_out_at' => $toDateTime($data['clock_out'] ?? null),
            'proposed_note'         => $data['note'] ?? null,
            'status'                => 'pending',
            'payload'               => [
                // 2系統で冗長保存：proposed_* は既存、payload は画面表示用
                'clock_in'  => $data['clock_in']  ?? null,
                'clock_out' => $data['clock_out'] ?? null,
                'breaks'    => $payloadBreaks,     // ← ここで休憩も保存
                'note'      => $data['note'] ?? null,
            ],
        ]);

        return redirect()
            ->route('attendance.detail', ['date' => $date->toDateString()]);
    }
}

