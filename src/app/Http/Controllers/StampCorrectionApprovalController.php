<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use Illuminate\Support\Facades\Log;


class StampCorrectionApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:admin', 'can:admin']);
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
    public function show(int $attendance_correct_request_id)
    {
        $req = CorrectionRequest::with(['user:id,name', 'attendanceDay'])->findOrFail($attendance_correct_request_id);

        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = $req->target_at
            ? Carbon::parse($req->target_at, $tz)
            : optional($req->attendanceDay?->work_date ? Carbon::parse($req->attendanceDay->work_date, $tz) : null);

        // 表示用（存在すれば new_* を優先 → 元の値 → 勤怠実績）
        $pick = function (...$c) {
            foreach ($c as $v) if ($v !== null && $v !== '') return $v;
            return null;
        };
        $hm   = function ($v) use ($tz) {
            if (empty($v)) return '-';
            try {
                return Carbon::parse($v, $tz)->format('H:i');
            } catch (\Throwable $e) {
                return $v;
            }
        };

        $a = $req->attendanceDay;
        $record = [
            'clock_in'     => $hm($pick($req->new_clock_in,     $req->clock_in,     optional($a)->clock_in_at)),
            'clock_out'    => $hm($pick($req->new_clock_out,    $req->clock_out,    optional($a)->clock_out_at)),
            'break1_start' => $hm($pick($req->new_break1_start, $req->break1_start)),
            'break1_end'   => $hm($pick($req->new_break1_end,   $req->break1_end)),
            'break2_start' => $hm($pick($req->new_break2_start, $req->break2_start)),
            'break2_end'   => $hm($pick($req->new_break2_end,   $req->break2_end)),
            'note'         => $pick($req->note, $req->reason),
        ];

        return view('stamp_correction_request.approve', compact('req', 'date', 'record'));
    }

    /** 承認実行（POST） */
    public function approve(Request $request, int $attendance_correct_request_id)
    {

        Log::info('APPROVE_POST_HIT', ['id' => $attendance_correct_request_id]);

        $tz = config('app.timezone', 'Asia/Tokyo');

        DB::transaction(function () use ($attendance_correct_request_id, $tz) {
            // ① 申請をロック
            $req = CorrectionRequest::lockForUpdate()->findOrFail($attendance_correct_request_id);
            if ($req->status === 'approved') return; // 冪等

            // ② 勤怠レコードを取得/作成
            $workDate = $req->target_at
                ? Carbon::parse($req->target_at, $tz)->toDateString()
                : now($tz)->toDateString();

            $attendance = AttendanceDay::firstOrCreate([
                'user_id'   => $req->requested_by,   // ※プロジェクトの外部キーに合わせる
                'work_date' => $workDate,
            ]);

            // ③ 存在するカラムだけ安全に上書き
            $setIf = function ($from, $to) use ($req, $attendance, $tz) {
                if (is_null($req->$from) || $req->$from === '') return;
                if (!Schema::hasColumn($attendance->getTable(), $to)) return; // 無い環境でも落とさない
                $attendance->$to = ($to === 'note')
                    ? $req->$from
                    : Carbon::parse($req->$from, $tz);
            };

            // new_* があれば new_* を優先
            $setIf($req->new_clock_in     !== null ? 'new_clock_in'     : 'clock_in',     'clock_in_at');
            $setIf($req->new_clock_out    !== null ? 'new_clock_out'    : 'clock_out',    'clock_out_at');
            $setIf($req->new_break1_start !== null ? 'new_break1_start' : 'break1_start', 'break1_start_at');
            $setIf($req->new_break1_end   !== null ? 'new_break1_end'   : 'break1_end',   'break1_end_at');
            $setIf($req->new_break2_start !== null ? 'new_break2_start' : 'break2_start', 'break2_start_at');
            $setIf($req->new_break2_end   !== null ? 'new_break2_end'   : 'break2_end',   'break2_end_at');
            $setIf('note', 'note');

            // ④ 合計分を再計算（存在する列だけ）
            $attendance = $this->recalcTotalsIfColumnsExist($attendance, $tz);

            $attendance->save();

            // ⑤ 申請を承認へ
            $req->status      = 'approved';
            if (Schema::hasColumn($req->getTable(), 'approved_at')) $req->approved_at = now($tz);
            if (Schema::hasColumn($req->getTable(), 'approved_by')) $req->approved_by = Auth::guard('admin')->id();
            $req->save();
        });

        return redirect()
            ->route('stamp_correction_request.approve', ['attendance_correct_request_id' => $attendance_correct_request_id])
            ->with('success', '承認しました。');
    }

    /** 勤怠の合計（分）をざっくり再計算 */
    private function recalcTotalsIfColumnsExist(AttendanceDay $a, string $tz): AttendanceDay
    {
        $has = fn($col) => Schema::hasColumn($a->getTable(), $col);

        $min = function ($s, $e) use ($a, $tz, $has) {
            if (!$has($s) || !$has($e)) return 0;
            $sv = $a->$s;
            $ev = $a->$e;
            return ($sv && $ev) ? Carbon::parse($sv, $tz)->diffInMinutes(Carbon::parse($ev, $tz), false) : 0;
        };

        $work = 0;
        if ($has('clock_in_at') && $has('clock_out_at')) {
            $work = $min('clock_in_at', 'clock_out_at');
        }

        $break = 0;
        $break += $min('break1_start_at', 'break1_end_at');
        $break += $min('break2_start_at', 'break2_end_at');

        $work = max(0, $work - max(0, $break));

        if ($has('total_work_minutes'))  $a->total_work_minutes  = $work;
        if ($has('total_break_minutes')) $a->total_break_minutes = max(0, $break);

        return $a;
    }
}
