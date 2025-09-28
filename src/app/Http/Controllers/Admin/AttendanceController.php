<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceDay;
use App\Models\User;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'can:admin']);
    }

    /**
     * 全社員の勤怠一覧
     * GET /admin/attendance  name: admin.attendance.index
     */
    public function index(Request $request)
    {
        $userId   = $request->query('user_id');
        $dateFrom = $request->query('from');
        $dateTo   = $request->query('to');

        $attendances = AttendanceDay::query()
            ->when($userId,  fn($q) => $q->where('user_id', $userId))
            ->when($dateFrom, fn($q) => $q->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('work_date', '<=', $dateTo))
            ->with('user:id,name')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(20);

        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        return view('admin.attendance.index', compact('attendances', 'users', 'userId', 'dateFrom', 'dateTo'));
    }

    /**
     * 勤怠詳細
     * GET /admin/attendance/{attendanceDay}  name: admin.attendance.show
     */
    public function show(AttendanceDay $attendanceDay)
    {
        return view('admin.attendance.show', ['attendance' => $attendanceDay]);
    }

    /**
     * スタッフ別勤怠一覧
     * GET /admin/staff/{user}/attendance  name: admin.staff.attendance.index
     */
    public function staffAttendance(Request $request, User $user)
    {
        $attendances = AttendanceDay::query()
            ->where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.staff.attendance.index', compact('user', 'attendances'));
    }
}
