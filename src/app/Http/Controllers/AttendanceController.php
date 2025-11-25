<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\AttendanceDetailRequest;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Illuminate\Support\Facades\DB;
use App\Models\CorrectionRequest;
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
        $now = \Carbon\Carbon::now($tz);

        // 1) 「出勤済み && 未退勤」を最優先
        $open = AttendanceDay::where('user_id', $user->id)
            ->whereNotNull('clock_in_at')      // ★追加：出勤済みに限定
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        // 2) それが無ければ「直近の勤怠レコード（前日でもOK）」を拾う
        //    → 退勤直後はここで昨日のレコードを拾い、画面は「退勤済」を表示できる
        $day = $open ?: AttendanceDay::where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->first();

        // 画面用ステート
        $state = 'before';
        $displayTime = $now->format('H:i');

        if ($day) {
            if ($day->clock_out_at) {
                $state = 'after'; // ★退勤済 → 「退勤済」を表示
            } else {
                $hasOpenBreak = $day->breaks()->whereNull('ended_at')->exists();
                if ($hasOpenBreak) {
                    $state = 'break';
                } elseif ($day->clock_in_at) {
                    $state = 'working'; // ★ここで「退勤」ボタンが出る
                    $displayTime = \Carbon\Carbon::parse($day->clock_in_at)->timezone($tz)->format('H:i');
                } else {
                    $state = 'before';
                }
            }
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

    private function resolveOpenDay(int $userId, \Carbon\Carbon $now): ?\App\Models\AttendanceDay
    {
        // 未退勤を最優先
        $open = \App\Models\AttendanceDay::where('user_id', $userId)
            ->whereNull('clock_out_at')
            ->orderByDesc('work_date')
            ->first();

        if ($open) return $open;

        // なければ「きょう」のレコード（存在しなければ null、clock-in 時に作る）
        return \App\Models\AttendanceDay::where('user_id', $userId)
            ->whereDate('work_date', $now->toDateString())
            ->first();
    }

    /** 退勤 */
    public function clockOut()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = \Carbon\Carbon::now($tz);

        $day = $this->resolveOpenDay($userId, $now);
        if (!$day || !$day->clock_in_at) {
            return back()->with('error', '退勤できる勤務がありません');
        }

        // 開いている休憩があれば閉じる
        if ($openBreak = $day->breaks()->whereNull('ended_at')->first()) {
            $openBreak->ended_at = $now;
            $openBreak->save();
        }

        $day->clock_out_at = $now;
        // ★ ここを一旦外す（DB側の定義が合うまで）
        // $day->status = 'checked_out';
        $day->save();

        return back()->with('status', 'clocked-out');
    }

    /** 休憩入り */
    public function breakIn()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = \Carbon\Carbon::now($tz);

        $day = $this->resolveOpenDay($userId, $now);
        if (!$day || !$day->clock_in_at || $day->clock_out_at) {
            return back()->with('error', '休憩開始できる勤務がありません');
        }

        // 既に未終了の休憩があれば何もしない（or エラー）
        if (!$day->breaks()->whereNull('ended_at')->exists()) {
            $day->breaks()->create(['started_at' => $now]);
        }

        $day->status = 'breaking';
        $day->save();

        return back()->with('status', 'break-started');
    }

    /** 休憩戻り */
    public function breakOut()
    {
        $userId = Auth::guard('web')->id();
        if (!$userId) abort(401);

        $tz  = config('app.timezone', 'Asia/Tokyo');
        $now = \Carbon\Carbon::now($tz);

        $day = $this->resolveOpenDay($userId, $now);
        if (!$day) return back()->with('error', '対象勤務がありません');

        $openBreak = $day->breaks()->whereNull('ended_at')->first();
        if ($openBreak) {
            $openBreak->ended_at = $now;
            $openBreak->save();
        }

        $day->status = 'working';
        $day->save();

        return back()->with('status', 'break-ended');
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
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz = config('app.timezone', 'Asia/Tokyo');

        $month = $request->query('month')
            ? Carbon::parse($request->query('month') . '-01', $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $start = $month->copy();
        $end   = $month->copy()->endOfMonth();

        $rows = AttendanceDay::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($d) => $d->work_date->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ad = $rows[$d->toDateString()] ?? null;

            // 休憩の「時刻表示」を組み立て（例: "12:00-12:30, 15:10-15:25"）
            $breakText = '';
            if ($ad && $ad->breaks->isNotEmpty()) {
                $parts = [];
                foreach ($ad->breaks as $b) {
                    $s = $b->started_at ? Carbon::parse($b->started_at, $tz)->format('H:i') : null;
                    $e = $b->ended_at   ? Carbon::parse($b->ended_at,   $tz)->format('H:i') : null;
                    if ($s || $e) {
                        $parts[] = trim(($s ?? '') . ($e ? "-$e" : ''), '-');
                    }
                }
                $breakText = implode(', ', array_filter($parts));
            }

            $days[] = [
                'date'         => $d->copy(),
                'clock_in'     => $ad && $ad->clock_in_at  ? Carbon::parse($ad->clock_in_at,  $tz)->format('H:i') : '',
                'clock_out'    => $ad && $ad->clock_out_at ? Carbon::parse($ad->clock_out_at, $tz)->format('H:i') : '',
                'break_total'  => $ad ? $this->formatMinutes($ad->total_break_minutes) : '',
                'break_text'   => $breakText,
            ];
        }

        return view('attendance.list', compact('month', 'days'));
    }

    // 無ければ追加（合計分の "HH:MM" 用）
    private function formatMinutes(?int $min): string
    {
        if ($min === null) return '';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%02d:%02d', $h, $m);
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

        // ---- ★ 承認待ち判定----
        $attendanceId = $attendance->id ?? null;

        if ($attendanceId) {
            $isPending = CorrectionRequest::where('requested_by', $user->id)
                ->where('attendance_day_id', $attendanceId)
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

    // App\Http\Controllers\AttendanceController 内に追加
    public function updateByDate(AttendanceDetailRequest $request, string $date)
    {
        $user = Auth::guard('web')->user();
        if (!$user) abort(401);

        $tz   = config('app.timezone', 'Asia/Tokyo');
        $dayC = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        // 対象日レコード
        $day = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $dayC->toDateString()],
            []
        );

        // ★ 承認待ちはユーザー更新をブロック（管理者は別で許可）
        if ($day->id) {
            $pending = CorrectionRequest::where('requested_by', $user->id)
                ->where('attendance_day_id', $day->id)
                ->where('status', 'pending')
                ->exists();
            if ($pending) {
                return back()->with('error', '承認待ちのため修正はできません。')->withInput();
            }
        }

        // ---- ここからは保存だけ（バリデーションはFormRequestで済んでいる）----
        $toDT = fn(?string $hm) => $hm
            ? Carbon::createFromFormat('Y-m-d H:i', $dayC->toDateString() . ' ' . $hm, $tz)->second(0)
            : null;

        $in   = $toDT($request->input('clock_in'));   // FormRequestでH:iは検証済み
        $out  = $toDT($request->input('clock_out'));
        $norm = $request->normalizedBreaks($dayC);    // ★ FormRequestに用意しておく

        DB::transaction(function () use ($day, $in, $out, $request, $norm, $tz) {
            // 1) 本体
            $day->clock_in_at  = $in;
            $day->clock_out_at = $out;
            $day->note         = (string)$request->input('note', '');
            $day->save();

            // 2) 休憩は全削除→差し替え（列名が started_at/ended_at の前提）
            $day->breaks()->delete();
            foreach ($norm as [$s, $e]) {
                $day->breaks()->create([
                    'started_at' => $s->copy(),
                    'ended_at'   => $e->copy(),
                ]);
            }

            // 3) 合計（秒→分切り捨て）
            $day->load('breaks');
            $breakSec = 0;
            foreach ($day->breaks as $bp) {
                if ($bp->started_at && $bp->ended_at) {
                    $s = Carbon::parse($bp->started_at, $tz);
                    $e = Carbon::parse($bp->ended_at,   $tz);
                    if ($e->gt($s)) $breakSec += $e->diffInSeconds($s);
                }
            }

            $adjOut = $out ? $out->copy() : null;
            if ($in && $adjOut && $adjOut->lt($in)) $adjOut->addDay(); // 0時跨ぎ

            $workSec = ($in && $adjOut) ? max(0, $adjOut->diffInSeconds($in) - $breakSec) : 0;

            if ($day->isFillable('total_break_minutes')) $day->total_break_minutes = intdiv($breakSec, 60);
            if ($day->isFillable('total_work_minutes'))  $day->total_work_minutes  = intdiv($workSec, 60);
            if ($day->isDirty()) $day->save();
        });

        return back()->with('success', '保存しました。');
    }
}
