<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StampCorrectionRequestController extends Controller
{
    /** 申請一覧（承認待ち / 承認済み） */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending'); // pending / approved

        $q = DB::table('correction_requests')
            ->join('attendance_days', 'attendance_days.id', '=', 'correction_requests.attendance_day_id')
            ->join('users', 'users.id', '=', 'correction_requests.requested_by')
            ->select([
                'correction_requests.id',
                'correction_requests.status',
                DB::raw('correction_requests.created_at as requested_at'),
                DB::raw('attendance_days.work_date as target_at'),
                DB::raw('correction_requests.proposed_note as reason'),
                DB::raw('users.name as user_name'),
                DB::raw('users.id as user_id'),
            ])
            ->when(in_array($status, ['pending', 'approved', 'rejected']), function ($q) use ($status) {
                $q->where('correction_requests.status', $status);
            });

        // 一般ユーザーは自分の申請のみ
        if (Auth::guard('web')->check() && !Auth::guard('admin')->check()) {
            $q->where('correction_requests.requested_by', Auth::id());
            $isAdmin = false;
        } else {
            $isAdmin = true;
        }

        $requests = $q->orderByDesc('correction_requests.created_at')->paginate(20);

        return view('stamp_correction_request.list', [
            'requests' => $requests,
            'status'   => $status,     // 'pending' or 'approved'
            'isAdmin'  => auth('admin')->check(),
        ]);
    }

    /** （必要なら）一般ユーザーからの申請作成 */
    public function store(Request $request, string $date)
    {
        // ここは元の実装に合わせてください（バリデ・作成など）
        // 触っていないならこのままで OK
        return back()->with('success', '申請を受け付けました。');
    }
}
