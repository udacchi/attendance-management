<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

// ← モデル名をユーザー環境に合わせて調整
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use App\Models\CorrectionRequest;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * 勤怠打刻 画面
     * GET /attendance/stamp  name: attendance.stamp
     * view: resources/views/attendance/stamp.blade.php
     */
    public function stamp(Request $request)
    {
        $today       = Carbon::now();
        $displayTime = $today->format('H:i:s');

        // デバッグ/仮置き用 ?state=before|working|break|after
        $state = $request->query('state');

        if (!in_array($state, ['before', 'working', 'break', 'after'], true)) {
            // 当日の最新レコードを取得（存在しないなら出勤前）
            $latest = AttendanceDay::query()
                ->where('user_id', Auth::id())
                ->whereDate('work_date', $today->toDateString())
                ->latest('id')
                ->first();

            if (!$latest) {
                $state = 'before';
            } else {
                // 列名の揺れに寛容に対応（どれか入っていればOK）
                $started  = $latest->started_at  ?? $latest->clock_in  ?? $latest->began_at ?? null;
                $finished = $latest->finished_at ?? $latest->clock_out ?? $latest->ended_at ?? null;

                // 進行中の休憩があるか（end系カラムのNULLで判定）
                $hasOngoingBreak = BreakPeriod::query()
                    ->where('user_id', Auth::id())
                    ->whereDate('work_date', $today->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('ended_at')
                            ->orWhereNull('end_time')
                            ->orWhereNull('finished_at');
                    })
                    ->exists();

                if ($finished) {
                    $state = 'after';
                } elseif ($hasOngoingBreak) {
                    $state = 'break';
                } elseif ($started) {
                    $state = 'working';
                } else {
                    $state = 'before';
                }
            }
        }

        return view('attendance.stamp', compact('today', 'displayTime', 'state'));
    }

    /**
     * 勤怠一覧（本人）
     * GET /attendance  name: attendance.index
     * view: resources/views/attendance/list.blade.php
     */
    public function index(Request $request)
    {
        $dateFrom = $request->query('from');
        $dateTo   = $request->query('to');

        $attendances = AttendanceDay::query()
            ->where('user_id', Auth::id())
            ->when($dateFrom, fn($q) => $q->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('work_date', '<=', $dateTo))
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('attendance.list', compact('attendances', 'dateFrom', 'dateTo'));
    }

    /**
     * 勤怠詳細（本人）
     * GET /attendance/{attendanceDay}  name: attendance.show
     * view: resources/views/attendance/show.blade.php
     */
    public function show(AttendanceDay $attendanceDay)
    {
        abort_unless($attendanceDay->user_id === Auth::id(), 403);

        // 最新の修正申請ステータス（列名/外部キーの揺れに対応）
        $latestReq = CorrectionRequest::query()
            ->where(function ($q) use ($attendanceDay) {
                $q->where('attendance_day_id', $attendanceDay->id)
                    ->orWhere('attendance_id', $attendanceDay->id);
            })
            ->latest('id')
            ->first();

        $status = $latestReq->status ?? 'none'; // pending | approved | rejected | none

        return view('attendance.show', [
            'attendance' => $attendanceDay,
            'status'     => $status,
        ]);
    }
}
