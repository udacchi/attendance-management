<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;   // ★ 追加
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller  // ★ クラス名修正
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * 申請一覧（一般ユーザー=自分の申請のみ / 管理者=全件）
     */
    public function index(Request $request)
    {
        $isAdmin = Gate::allows('admin');  // ★ ここで判定（isAdmin() を呼ばない）

        $requests = CorrectionRequest::query()
            ->when(!$isAdmin, fn($q) => $q->where('requested_by', Auth::id())) // ★ requested_by を使用
            ->latest('id')
            ->paginate(10);

        return view('stamp_correction_request.list', compact('requests', 'isAdmin'));
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
