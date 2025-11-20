<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;
use App\Models\CorrectionRequest;

class AttendanceController extends Controller
{
    /** 打刻画面（GET /attendance/stamp）
     *  Blade が期待する: state / today / displayTime
     */
    public function stamp(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz);

        // きょうの勤怠を取得
        $day = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();

        // 状態は status カラムを優先。無ければ時計情報から推定。
        $state = $day->status ?? null;
        if (!$state) {
            if (!$day || !$day->clock_in_at) {
                $state = 'before';
            } elseif ($day->clock_in_at && !$day->clock_out_at) {
                $hasOpenBreak = $day->breaks?->whereNull('ended_at')->isNotEmpty();
                $state = $hasOpenBreak ? 'break' : 'working';
            } else {
                $state = 'after';
            }
        }

        return view('attendance.stamp', [
            'state'       => $state,               // 'before' | 'working' | 'break' | 'after'
            'today'       => $now,                 // Carbon
            'displayTime' => $now->format('H:i') // 画面の時刻表示
        ]);
    }

    /** 打刻アクション（POST /attendance/punch）
     *  Blade 側から name="action" で以下のいずれかが来る想定：
     *  clock-in | break-start | break-end | clock-out
     */
    public function punch(Request $request)
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        // Laravel 8/9 互換：validate で許容値を固定
        $validated = $request->validate([
            'action' => 'required|in:clock-in,break-start,break-end,clock-out',
        ]);
        $action = $validated['action'];

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = Carbon::now($tz)->second(0);

        // きょうの勤怠（なければ作成）
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

                // 休憩分（total_break_minutes 優先、未設定なら明細から算出）
                $breakMin = (int)($day->total_break_minutes ?? 0);
                if ($breakMin === 0) {
                    $breakMin = 0;
                    foreach ($day->breaks as $bp) {
                        if ($bp->started_at && $bp->ended_at) {
                            $breakMin += Carbon::parse($bp->ended_at)->diffInMinutes(Carbon::parse($bp->started_at));
                        }
                    }
                    $day->total_break_minutes = $breakMin;
                }

                // 実働
                if ($day->clock_in_at && $day->clock_out_at) {
                    $worked = Carbon::parse($day->clock_out_at)->diffInMinutes(
                        Carbon::parse($day->clock_in_at),
                        false
                    ) - $breakMin;
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
        $user = Auth::guard('web')->user(); // 一般ユーザー
        if (!$user) abort(401);

        $tz = config('app.timezone', 'Asia/Tokyo');
        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        // 全日初期化（「-」表示用）
        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = [
                'date'        => $key,
                'clock_in'    => null,
                'clock_out'   => null,
                'break_total' => null,
                'work_total'  => null,
            ];
        }

        // 当月分を取得（休憩計算用に breaks を eager load）
        $rows = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        foreach ($rows as $r) {
            $key = $r->work_date->toDateString();
            if (!isset($days[$key])) continue;

            // 出勤・退勤（Asia/Tokyo で取得）
            $cin  = $r->clock_in_at  ? Carbon::parse($r->clock_in_at)->timezone($tz)  : null;
            $cout = $r->clock_out_at ? Carbon::parse($r->clock_out_at)->timezone($tz) : null;
            
            // 休憩合計（分）— 秒は完全無視（分に切り捨て）
            $breakMin = 0;
            $breaks = $r->relationLoaded('breaks') ? $r->breaks : $r->breaks()->get();
            foreach ($breaks as $bp) {
                $start = $bp->started_at ?? $bp->start_time ?? $bp->start_at ?? $bp->begin_at ?? null;
                $end   = $bp->ended_at   ?? $bp->end_time   ?? $bp->end_at   ?? $bp->finish_at ?? null;
                if ($start && $end) {
                    $s = Carbon::parse($start)->timezone($tz);
                    $e = Carbon::parse($end)->timezone($tz);

                    // 秒を無視して “分だけ” の差分（マイナスは0）
                    $sMin = $s->hour * 60 + $s->minute;
                    $eMin = $e->hour * 60 + $e->minute;
                    if ($eMin < $sMin) $eMin += 24 * 60; // 日跨ぎ対応
                    $diff = $eMin - $sMin;
                    if ($diff > 0) $breakMin += $diff;
                }
            }

            // 実働（分）＝(退勤−出勤)−休憩（いずれか欠けてたら null）
            $workedMin = null;
            if ($cin && $cout) {
                $cinMin  = $cin->hour  * 60 + $cin->minute;
                $coutMin = $cout->hour * 60 + $cout->minute;
                if ($coutMin < $cinMin) $coutMin += 24 * 60; // 日跨ぎ対応
                $gross = max(0, $coutMin - $cinMin);
                $workedMin = max(0, $gross - $breakMin);
            }

            // 画面表示用（0分のときも "00:00" を出す＝消えない）
            $days[$key] = [
                'date'        => $key,
                'clock_in'    => $cin  ? $cin->format('H:i')  : null,
                'clock_out'   => $cout ? $cout->format('H:i') : null,
                'break_total' => sprintf('%02d:%02d', intdiv($breakMin, 60), $breakMin % 60),
                'work_total'  => is_null($workedMin) ? null : sprintf('%02d:%02d', intdiv($workedMin, 60), $workedMin % 60),
            ];
        }

        return view('attendance.list', [
            'month' => $month,
            'days'  => array_values($days),
        ]);
    }


    /** 勤怠詳細（/attendance/{date}） */
    public function detail(string $date)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        // 対象日の勤怠 + 休憩
        $attendance = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $day->toDateString())
            ->first();

        // ---- record 配列を整形（今まで説明した通り）----
        $record = [
            'user_id'   => $user->id,
            'name'      => $user->name ?? '',
            'clock_in'  => '',
            'clock_out' => '',
            'note'      => '',
            'breaks'    => [],
        ];

        if ($attendance) {
            $record['clock_in']  = $attendance->clock_in_at
                ? Carbon::parse($attendance->clock_in_at)->tz($tz)->format('H:i')
                : '';
            $record['clock_out'] = $attendance->clock_out_at
                ? Carbon::parse($attendance->clock_out_at)->tz($tz)->format('H:i')
                : '';
            $record['note']      = $attendance->note ?? '';

            $breaks = $attendance->breaks->sortBy('started_at')->values();
            foreach ($breaks as $bp) {
                $record['breaks'][] = [
                    'start' => $bp->started_at
                        ? Carbon::parse($bp->started_at)->tz($tz)->format('H:i')
                        : '',
                    'end'   => $bp->ended_at
                        ? Carbon::parse($bp->ended_at)->tz($tz)->format('H:i')
                        : '',
                ];
            }
        }

        // 休憩 +1 行分
        $record['breaks'][] = ['start' => '', 'end' => ''];

        // ---- ★ 承認待ち判定（work_date を使わない）----
        $attendanceId = $attendance->id ?? null;

        if ($attendanceId) {
            $isPending = CorrectionRequest::where('requested_by', $user->id)
                ->where('attendance_day_id', $attendanceId) // ← ここで紐づけ
                ->where('status', 'pending')
                ->exists();
        } else {
            $isPending = false;
        }

        return view('attendance.detail', [
            'date'      => $day,
            'record'    => $record,
            'isPending' => $isPending,
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

    /** 休憩開始 */
    public function breakStart()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $now = Carbon::now('Asia/Tokyo');
        $day = AttendanceDay::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $now->toDateString()]
        );

        $day->breaks()->create(['started_at' => $now]); // ended_at は後で埋める
        $day->status = 'break';
        $day->save();

        return back()->with('status', 'break-started');
    }

    /** 休憩終了 */
    public function breakEnd()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $now = Carbon::now('Asia/Tokyo');
        $day = AttendanceDay::where('user_id', $userId)->whereDate('work_date', $now->toDateString())->firstOrFail();

        $open = $day->breaks()->whereNull('ended_at')->latest('started_at')->first();
        if ($open) {
            $open->ended_at = $now;
            $open->save();
        }
        $day->status = 'working';
        $day->save();

        return back()->with('status', 'break-ended');
    }

    /** 退勤（集計も確定） */
    public function clockOut()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $now = Carbon::now('Asia/Tokyo');
        $day = AttendanceDay::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $now->toDateString()]
        );

        if (!$day->clock_out_at) {
            $day->clock_out_at = $now;
        }

        // 休憩合算（total_break_minutes が未確定なら break_periods から算出）
        $breakMin = (int)($day->total_break_minutes ?? 0);
        if ($breakMin === 0) {
            $breakMin = 0;
            foreach ($day->breaks as $bp) {
                if ($bp->started_at && $bp->ended_at) {
                    $breakMin += Carbon::parse($bp->ended_at)->diffInMinutes(Carbon::parse($bp->started_at));
                }
            }
            $day->total_break_minutes = $breakMin;
        }

        // 実働
        if ($day->clock_in_at && $day->clock_out_at) {
            $worked = Carbon::parse($day->clock_out_at)->diffInMinutes(Carbon::parse($day->clock_in_at), false) - $breakMin;
            $day->total_work_minutes = max(0, $worked);
        }

        $day->status = 'after';
        $day->save();

        return back()->with('status', 'clocked-out');
    }

    // ================= ヘルパ =================

    /** 今日の状態を判定: before|working|break|after */
    private function detectStateForToday(int $userId, string $date, AttendanceDay $attendance): string
    {
        $started  = $this->getColumn($attendance, ['started_at', 'clock_in', 'began_at']);
        $finished = $this->getColumn($attendance, ['finished_at', 'clock_out', 'ended_at']);

        if ($finished) {
            return 'after';
        }
        if ($this->findOngoingBreak($userId, $date)) {
            return 'break';
        }
        if ($started) {
            return 'working';
        }
        return 'before';
    }

    /** 進行中の休憩(終了カラムがNULL)を1件返す */
    private function findOngoingBreak(int $userId, string $date): ?BreakPeriod
    {
        $query = BreakPeriod::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $date);

        // 終了候補いずれかがNULL
        $query->where(function ($q) {
            $q->whereNull('ended_at')
                ->orWhereNull('end_time')
                ->orWhereNull('finished_at');
        });

        return $query->latest('id')->first();
    }

    /** 最初に見つかった既存カラムへ値をセット */
    private function setColumn($model, array $candidates, $value): void
    {
        $table = $model->getTable();
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                $model->{$col} = $value;
                return;
            }
        }
        // どれも存在しない場合は安全側で何もしない（スキーマに揺れが大きい環境向け）
    }

    /** 最初に見つかった既存カラムの値を取得 */
    private function getColumn($model, array $candidates)
    {
        $table = $model->getTable();
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                $val = $model->{$col} ?? null;
                if (!is_null($val)) return $val;
            }
        }
        return null;
    }

    /** 休憩累計秒の加算（候補：total_break_seconds / break_total_seconds / break_seconds） */
    private function incrementBreakTotal(AttendanceDay $attendance, int $addSec): void
    {
        $table = $attendance->getTable();
        foreach (['total_break_seconds', 'break_total_seconds', 'break_seconds'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $attendance->{$col} = (int) ($attendance->{$col} ?? 0) + $addSec;
                return;
            }
        }
    }
}
