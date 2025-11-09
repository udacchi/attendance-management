<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller  // ★ クラス名修正
{
    /**
     * 申請一覧（一般ユーザー=自分の申請のみ / 管理者=全件）
     */
    public function index(Request $request)
    {
        $isAdmin = Auth::guard('admin')->check();

        if ($isAdmin) {
            // 管理者：全申請
            $requests = CorrectionRequest::query()
                ->latest()
                ->paginate(20);
        } else {
            // 一般ユーザー：自分の申請のみ
            $uid = Auth::id(); // web ガード
            $requests = CorrectionRequest::query()
                ->where('requested_by', $uid)   // カラム名はプロジェクトに合わせて
                ->latest()
                ->paginate(20);
        }

        return view('stamp_correction_request.list', [
            'requests' => $requests,
            'isAdmin'  => $isAdmin,
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
