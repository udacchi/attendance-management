<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorrectionRequest;
use Carbon\Carbon;

class StampCorrectionApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'can:admin']);
    }

    /**
     * 修正申請 承認一覧
     * GET /admin/stamp-corrections  name: admin.corrections.index
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending/approved/rejected

        $requests = CorrectionRequest::query()
            ->when($status, fn($q) => $q->where('status', $status))
            ->with('user:id,name')
            ->latest('id')
            ->paginate(20);

        return view('admin.stamp_corrections.index', compact('requests', 'status'));
    }

    /**
     * 承認画面（GET）
     */
    public function show($attendance_correct_request_id)
    {
        $req = CorrectionRequest::with(['user:id,name', 'attendanceDay'])->findOrFail($attendance_correct_request_id);

        $tz   = config('app.timezone', 'Asia/Tokyo');

        // 値が無ければ順にフォールバックするヘルパ
        $pick = function (...$candidates) {
            foreach ($candidates as $v) if (!is_null($v) && $v !== '') return $v;
            return null;
        };
        // "H:i" で表示整形（null/既に "09:00" ならそのまま）
        $hm = function ($v) use ($tz) {
            if (empty($v)) return null;
            try {
                // Carbon/文字列どちらでも parse
                return Carbon::parse($v, $tz)->format('H:i');
            } catch (\Throwable $e) {
                return $v;
            }
        };

        // 日付（request の target_at → attendanceDay の work_date の順で）
        $date = $pick($req->target_at, optional($req->attendanceDay)->work_date);
        $date = $date ? Carbon::parse($date, $tz) : null;

        // 表示用レコード（カラム名の揺れを吸収）
        $record = [
            'clock_in'     => $hm($pick($req->clock_in, $req->new_clock_in, optional($req->attendanceDay)->clock_in_at)),
            'clock_out'    => $hm($pick($req->clock_out, $req->new_clock_out, optional($req->attendanceDay)->clock_out_at)),
            'break1_start' => $hm($pick($req->break1_start, $req->new_break1_start)),
            'break1_end'   => $hm($pick($req->break1_end, $req->new_break1_end)),
            'break2_start' => $hm($pick($req->break2_start, $req->new_break2_start)),
            'break2_end'   => $hm($pick($req->break2_end, $req->new_break2_end)),
            'note'         => $pick($req->note, $req->reason),
        ];

        return view('stamp_correction_request.approve', compact('req', 'date', 'record'));
    }

    /**
     * 承認処理（POST）
     */
    public function approve(Request $request, $attendance_correct_request_id)
    {
        $correction = CorrectionRequest::findOrFail($attendance_correct_request_id);
        $correction->status = 'approved';
        $correction->save();

        return redirect()->route('stamp_correction_request.list')
            ->with('success', '申請を承認しました。');
    }
}
