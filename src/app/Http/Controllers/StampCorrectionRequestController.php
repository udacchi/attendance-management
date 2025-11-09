<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller  // ★ クラス名修正
{
    /**
     * 申請一覧（一般ユーザー=自分の申請のみ / 管理者=全件）
     */
    public function index(Request $request)
    {
        $isAdmin = Auth::guard('admin')->check();
        $status  = $request->query('status', 'pending'); // pending | approved

        $q = CorrectionRequest::query()
            ->leftJoin('users', 'users.id', '=', 'correction_requests.requested_by')
            ->leftJoin('attendance_days', 'attendance_days.id', '=', 'correction_requests.attendance_day_id')
            ->select([
                'correction_requests.*',
                'users.name as user_name',
                DB::raw('correction_requests.created_at as requested_at'),
                // ★ 対象日を勤怠テーブルから取得して alias を target_at に
                DB::raw('attendance_days.work_date as target_at'),
            ])
            ->when(!$isAdmin, function ($q) {
                // 一般ユーザー：自分の申請のみ
                $q->where('correction_requests.requested_by', Auth::id());
                // 万一 requested_by が未設定でも念のため保険（不要なら削除可）
                // $q->orWhere('attendance_days.user_id', Auth::id());
            })
            ->when(
                $status === 'approved',
                fn($q) => $q->where('correction_requests.status', 'approved'),
                fn($q) => $q->where('correction_requests.status', 'pending')
            )
            ->orderByDesc('correction_requests.id');

        $requests = $q->paginate(20)->withQueryString();

        return view('stamp_correction_request.list', [
            'requests' => $requests,
            'isAdmin'  => $isAdmin,
            'status'   => $status,
        ]);
    }

    /**
     * 勤怠詳細（/attendance/{date}）からの「修正」クリックで承認待ち申請を作成
     * ルート名: attendance.request  POST /attendance/{date}/request
     */
    public function store(Request $request, string $date)
    {
        $userId   = Auth::id();
        $tz       = config('app.timezone', 'Asia/Tokyo');
        $workDate = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        // 1) 当日の AttendanceDay を取得（ユーザー x 日付）
        $attendance = AttendanceDay::where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->firstOrFail();

        // 2) 既に pending の申請があるか（attendance_day_id で判定）
        $exists = CorrectionRequest::where('requested_by', $userId)
            ->where('attendance_day_id', $attendance->id)
            ->where('status', 'pending') // ← 文字列は必ずクォート
            ->exists();

        if (!$exists) {
            CorrectionRequest::create([
                'requested_by'      => $userId,
                'attendance_day_id' => $attendance->id,
                'status'            => 'pending',
                'note'              => $request->input('note'),
            ]);
        }

        return redirect()
            ->route('attendance.detail', ['date' => $date])
            ->with('flash', '修正申請を送信しました。（承認待ち）');
    }
}
