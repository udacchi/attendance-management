<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;

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
        $now = Carbon::now($tz);

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
        $user = Auth::guard('web')->user();     // ← webガードを明示
        if (!$user) abort(401);

        $tz    = config('app.timezone', 'Asia/Tokyo');
        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        // 全日初期化（- を出すため）
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

        // 当月分を取得（休憩は total_break_minutes 優先、無ければ break_periods から合算）
        $rows = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        foreach ($rows as $r) {
            $key = $r->work_date->toDateString();
            if (!isset($days[$key])) continue;

            $cin  = $r->clock_in_at ? Carbon::parse($r->clock_in_at)->tz($tz) : null;
            $cout = $r->clock_out_at ? Carbon::parse($r->clock_out_at)->tz($tz) : null;

            // 休憩分（分）
            $breakMin = (int)($r->total_break_minutes ?? 0);
            if ($breakMin === 0 && $r->relationLoaded('breaks')) {
                foreach ($r->breaks as $bp) {
                    if ($bp->started_at && $bp->ended_at) {
                        $breakMin += Carbon::parse($bp->ended_at)->diffInMinutes(Carbon::parse($bp->started_at));
                    }
                }
            }
            $breakStr = $breakMin ? sprintf('%d:%02d', intdiv($breakMin, 60), $breakMin % 60) : null;

            // 実働
            $workStr = null;
            if ($cin && $cout) {
                $worked = $cout->diffInMinutes($cin, false) - $breakMin;
                if ($worked < 0) $worked = 0;
                $workStr = sprintf('%02d:%02d', intdiv($worked, 60), $worked % 60);
            }

            $days[$key] = [
                'date'        => $key,
                'clock_in'    => $cin  ? $cin->format('H:i')  : null,
                'clock_out'   => $cout ? $cout->format('H:i') : null,
                'break_total' => $breakStr,
                'work_total'  => $workStr,
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

        $tz = config('app.timezone', 'Asia/Tokyo');
        $day = Carbon::createFromFormat('Y-m-d', $date, $tz);

        $record = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $day->toDateString())
            ->first();

        // 休憩合算（表示用）
        // ビュー期待の配列へ整形
        $recordArr = [];
        if ($record) {
            $recordArr['name']      = $user->name ?? '';
            $recordArr['clock_in']  = $record->clock_in_at  ? Carbon::parse($record->clock_in_at)->tz($tz)->format('H:i') : '';
            $recordArr['clock_out'] = $record->clock_out_at ? Carbon::parse($record->clock_out_at)->tz($tz)->format('H:i') : '';

            // 休憩は最大2枠だけ表示（必要に応じて増やしてOK）
            $breaks = $record->breaks->sortBy('started_at')->values();
            $recordArr['break1_start'] = isset($breaks[0]) && $breaks[0]->started_at
                ? Carbon::parse($breaks[0]->started_at)->tz($tz)->format('H:i') : '';
            $recordArr['break1_end']   = isset($breaks[0]) && $breaks[0]->ended_at
                ? Carbon::parse($breaks[0]->ended_at)->tz($tz)->format('H:i')   : '';
            $recordArr['break2_start'] = isset($breaks[1]) && $breaks[1]->started_at
                ? Carbon::parse($breaks[1]->started_at)->tz($tz)->format('H:i') : '';
            $recordArr['break2_end']   = isset($breaks[1]) && $breaks[1]->ended_at
                ? Carbon::parse($breaks[1]->ended_at)->tz($tz)->format('H:i')   : '';

            $recordArr['note'] = $record->note ?? '';
        }

        // 承認待ちフラグ（運用に合わせて判定を差し替え）
        $isPending = false;

        return view('attendance.detail', [
            'date'      => $day,
            'record'    => $recordArr,
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
