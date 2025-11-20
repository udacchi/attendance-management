<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use App\Http\Requests\AttendanceDetailRequest;
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
    public function store(AttendanceDetailRequest $request, $date)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $user = Auth::user();
        $day  = Carbon::parse($date, $tz)->startOfDay();

        // 対象の勤怠を取得/作成
        $attendanceDay = AttendanceDay::firstOrCreate(
            [
                'user_id'   => $user->id,
                'work_date' => $day->toDateString(),
            ],
            []
        );

        // ★ 既に同じ勤怠に対する pending 申請があるか？
        $alreadyPending = CorrectionRequest::where('requested_by', $user->id)
            ->where('attendance_day_id', $attendanceDay->id)   // ← work_date ではなく id
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return back()
                ->with('error', '承認待ちのため修正はできません。')
                ->withInput();
        }

        // 以降、before/after payload 作成はそのまま
        $before = [ /* ... */];
        $after  = [ /* ... */];

        $correction = new CorrectionRequest();
        $correction->attendance_day_id = $attendanceDay->id;
        $correction->requested_by      = $user->id;
        $correction->status            = 'pending';
        $correction->before_payload    = json_encode($before);
        $correction->after_payload     = json_encode($after);
        $correction->save();

        return redirect()
            ->route('attendance.detail', ['date' => $day->toDateString()])
            ->with('status', '修正申請を送信しました。');
    }
}
