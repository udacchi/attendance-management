<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;

class StampCorrectionApprovalController extends Controller
{
    public function __construct()
    {
        // ★ POST の approve だけ管理者必須にする
        $this->middleware(['auth:admin', 'can:admin'])->only('approve');

        // ★ GET の show は web/admin どちらでもOK
        $this->middleware('auth.any')->only('show');
    }

    /** 承認画面（GET） */
    public function show(int $attendance_correct_request_id)
    {
        $isAdmin = Auth::guard('admin')->check();
        $webUser = Auth::guard('web')->user();

        if (!$isAdmin && !$webUser) {
            abort(401);
        }

        $req = CorrectionRequest::with(['user:id,name', 'attendanceDay'])
            ->findOrFail($attendance_correct_request_id);

        // 一般ユーザーは「自分の申請だけ」閲覧可
        if (!$isAdmin) {
            $uidCol = Schema::hasColumn('correction_requests', 'requested_user_id')
                ? 'requested_user_id'
                : (Schema::hasColumn('correction_requests', 'requested_by')
                    ? 'requested_by'
                    : 'user_id');

            if ($req->{$uidCol} !== $webUser->id) {
                abort(403);
            }
        }

        $req = CorrectionRequest::with(['user:id,name', 'attendanceDay'])->findOrFail($attendance_correct_request_id);

        $tz = config('app.timezone', 'Asia/Tokyo');

        // 対象日（payload/列差異に頑丈に）
        $date = $req->target_at
            ? Carbon::parse($req->target_at, $tz)
            : ($req->attendanceDay && $req->attendanceDay->work_date
                ? Carbon::parse($req->attendanceDay->work_date, $tz)
                : Carbon::now($tz));

        $status    = $req->status ?? null;
        $isPending = ($status === 'pending');

        // 表示用: HH:MM か日時を H:i に、'--:--' は空に
        $hm = function ($v) use ($tz) {
            if ($v === null) return '';
            if (is_string($v)) {
                $t = trim($v);
                if ($t === '' || $t === '--:--') return '';
                if (preg_match('/^\d{1,2}:\d{2}$/', $t)) return $t;
            }
            try {
                return Carbon::parse($v, $tz)->format('H:i');
            } catch (\Throwable $e) {
                return '';
            }
        };

        // 候補から最初の非空を返す
        $firstNonEmpty = function (...$vals) {
            foreach ($vals as $v) {
                if ($v === null) continue;
                $s = is_string($v) ? trim($v) : $v;
                if ($s !== '' && $s !== '--:--') return $v;
            }
            return null;
        };

        $a = $req->attendanceDay;

        // payload を安全に配列化
        $payload = $req->payload;
        if (!is_array($payload)) {
            $payload = json_decode($payload ?? '', true);
            if (!is_array($payload)) $payload = [];
        }

        /** 休憩の復元 **/
        $breaks = [];
        if (!empty($payload['breaks']) && is_array($payload['breaks'])) {
            // 1) 申請 payload（最優先）
            foreach ($payload['breaks'] as $b) {
                $breaks[] = ['start' => $hm($b['start'] ?? null), 'end' => $hm($b['end'] ?? null)];
            }
        } else {
            // 2) 実績（breakPeriods / breaks どちらでも）
            $rel = null;
            if ($a) {
                $rel = method_exists($a, 'breakPeriods') ? 'breakPeriods' : (method_exists($a, 'breaks') ? 'breaks' : null);
                if ($rel) $a->loadMissing($rel);
            }
            if ($rel && $a->$rel) {
                // 列名の違いを吸収
                foreach ($a->$rel as $bp) {
                    $s = $bp->break_start_at ?? $bp->started_at ?? $bp->start_at ?? null;
                    $e = $bp->break_end_at   ?? $bp->ended_at   ?? $bp->end_at   ?? null;
                    $breaks[] = ['start' => $hm($s), 'end' => $hm($e)];
                }
            }
        }
        // 空行（start/end 両方空）は除去
        $breaks = array_values(array_filter($breaks, fn($b) => ($b['start'] ?? '') !== '' || ($b['end'] ?? '') !== ''));
        // 承認待ちの時だけ、見やすさ用に末尾へ空1行
        if ($isPending) $breaks[] = ['start' => '', 'end' => ''];

        /** 出勤・退勤 **/
        $clockIn = $firstNonEmpty(
            $payload['clock_in'] ?? null,
            $req->proposed_clock_in_at ?? null,
            $req->new_clock_in ?? null,   // 旧コード互換
            $req->clock_in ?? null,
            optional($a)->clock_in_at
        );
        $clockOut = $firstNonEmpty(
            $payload['clock_out'] ?? null,
            $req->proposed_clock_out_at ?? null,
            $req->new_clock_out ?? null,  // 旧コード互換
            $req->clock_out ?? null,
            optional($a)->clock_out_at
        );

        /** 備考（行単位で重複除去して結合） **/
        $noteParts = array_filter([
            $payload['note'] ?? null,
            $req->proposed_note ?? null,
            $req->reason ?? null,
            optional($a)->note,
        ], fn($v) => $v !== null && trim((string)$v) !== '');

        $split = fn(string $t) => preg_split('/\R/u', $t);
        $seen = [];
        $lines = [];
        foreach ($noteParts as $t) {
            foreach ($split((string)$t) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $key = mb_strtolower($line);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $lines[] = $line;
                }
            }
        }
        $noteMerged = implode("\n", $lines);

        // Blade へ渡す表示用レコード
        $record = [
            'name'      => optional($req->user)->name,
            'clock_in'  => $hm($clockIn),
            'clock_out' => $hm($clockOut),
            'note'      => $noteMerged,
            'breaks'    => $breaks,
        ];

        return view('stamp_correction_request.approve', compact('req', 'date', 'record', 'isPending'));
    }


    /** 承認実行（POST） */
    public function approve(Request $request, int $attendance_correct_request_id)
    {
        Log::info('APPROVE_POST_HIT', ['id' => $attendance_correct_request_id]);
        $tz = config('app.timezone', 'Asia/Tokyo');

        DB::transaction(function () use ($attendance_correct_request_id, $tz) {
            $req = CorrectionRequest::lockForUpdate()->findOrFail($attendance_correct_request_id);
            if ($req->status === 'approved') return;

            $workDate = $req->target_at
                ? Carbon::parse($req->target_at, $tz)->toDateString()
                : now($tz)->toDateString();

            $a = AttendanceDay::firstOrCreate([
                'user_id'   => $req->requested_by,
                'work_date' => $workDate,
            ]);

            $setIf = function ($from, $to) use ($req, $a, $tz) {
                if (is_null($req->$from) || $req->$from === '') return;
                if (!Schema::hasColumn($a->getTable(), $to)) return;
                $a->$to = ($to === 'note') ? $req->$from : Carbon::parse($req->$from, $tz);
            };

            $setIf($req->new_clock_in     !== null ? 'new_clock_in'     : 'clock_in',     'clock_in_at');
            $setIf($req->new_clock_out    !== null ? 'new_clock_out'    : 'clock_out',    'clock_out_at');
            $setIf($req->new_break1_start !== null ? 'new_break1_start' : 'break1_start', 'break1_start_at');
            $setIf($req->new_break1_end   !== null ? 'new_break1_end'   : 'break1_end',   'break1_end_at');
            $setIf($req->new_break2_start !== null ? 'new_break2_start' : 'break2_start', 'break2_start_at');
            $setIf($req->new_break2_end   !== null ? 'new_break2_end'   : 'break2_end',   'break2_end_at');
            $setIf('note', 'note');

            // 合計分のざっくり再計算（列があるときのみ）
            $min = function ($s, $e) use ($a, $tz) {
                $sv = $a->$s ?? null;
                $ev = $a->$e ?? null;
                return ($sv && $ev) ? Carbon::parse($sv, $tz)->diffInMinutes(Carbon::parse($ev, $tz), false) : 0;
            };
            if (Schema::hasColumn($a->getTable(), 'clock_in_at') && Schema::hasColumn($a->getTable(), 'clock_out_at')) {
                $work  = max(0, $min('clock_in_at', 'clock_out_at'));
                $break = $min('break1_start_at', 'break1_end_at') + $min('break2_start_at', 'break2_end_at');
                if (Schema::hasColumn($a->getTable(), 'total_work_minutes'))  $a->total_work_minutes  = max(0, $work - max(0, $break));
                if (Schema::hasColumn($a->getTable(), 'total_break_minutes')) $a->total_break_minutes = max(0, $break);
            }

            $a->save();

            $req->status = 'approved';
            if (Schema::hasColumn($req->getTable(), 'approved_at')) $req->approved_at = now($tz);
            if (Schema::hasColumn($req->getTable(), 'approved_by')) $req->approved_by = Auth::guard('admin')->id();
            $req->save();
        });

        return redirect()
            ->route('stamp_correction_request.approve', ['attendance_correct_request_id' => $attendance_correct_request_id])
            ->with('success', '承認しました。');
    }
}
