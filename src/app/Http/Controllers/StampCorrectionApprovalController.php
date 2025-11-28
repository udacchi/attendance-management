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
        $this->middleware(['auth:admin', 'can:admin']);
    }

    /** 承認画面（GET） */
    public function show(int $attendance_correct_request_id)
    {
        $req = CorrectionRequest::with(['user:id,name', 'attendanceDay'])
            ->findOrFail($attendance_correct_request_id);

        $tz = config('app.timezone', 'Asia/Tokyo');

        // 対象日
        $date = $req->target_at
            ? Carbon::parse($req->target_at, $tz)
            : ($req->attendanceDay && $req->attendanceDay->work_date
                ? Carbon::parse($req->attendanceDay->work_date, $tz)
                : Carbon::now($tz));

        $status    = $req->status ?? null;
        $isPending = ($status === 'pending');

        // 表示用ヘルパ
        $pick = function (...$c) {
            foreach ($c as $v) if ($v !== null && $v !== '') return $v;
            return null;
        };
        $hm   = function ($v) use ($tz) {
            if (empty($v)) return '';
            try {
                return Carbon::parse($v, $tz)->format('H:i');
            } catch (\Throwable $e) {
                return '';
            }
        };

        $a = $req->attendanceDay;

        // ---- 休憩の復元ロジック ---- //
        $breaks = [];

        // 1) 申請 payload に休憩が入っている場合（これを最優先で表示）
        if (!empty($req->payload['breaks']) && is_array($req->payload['breaks'])) {
            foreach ($req->payload['breaks'] as $b) {
                $breaks[] = [
                    'start' => $hm($b['start'] ?? null),
                    'end'   => $hm($b['end']   ?? null),
                ];
            }
        }

        // 2) payload に無ければ、AttendanceDay の実績 break を使用
        elseif ($req->attendanceDay && $req->attendanceDay->breaks) {
            foreach ($req->attendanceDay->breaks as $bp) {
                $breaks[] = [
                    'start' => $hm($bp->started_at),
                    'end'   => $hm($bp->ended_at),
                ];
            }
        }

        // ③実データの空行を除去（start と end が両方空の行）
        $breaks = array_values(array_filter($breaks, function ($b) {
            $s = $b['start'] ?? '';
            $e = $b['end']   ?? '';
            return ($s !== '' || $e !== '');
        }));

        // ④最後に必ず空の休憩行を 1 行追加
        $breaks[] = ['start' => '', 'end' => ''];

        // ----- 備考の統合（表示用） -----
        // payload を安全に配列化
        $payload = $req->payload;
        if (!is_array($payload)) {
            $payload = json_decode($payload ?? '', true);
            if (!is_array($payload)) $payload = [];
        }

        // 候補を集める（空は除外）
        $noteParts = array_filter([
            $req->proposed_note ?? null,
            $req->reason ?? null,
            $payload['note'] ?? null,
            optional($req->attendanceDay)->note,
        ], fn($v) => $v !== null && $v !== '');

        // --- 行単位で重複を除去 ---
        $split = fn(string $t) => preg_split('/\R/u', $t); // 改行で分割（\r\n,\r,\n 全対応）
        $seen = [];
        $lines = [];
        foreach ($noteParts as $t) {
            foreach ($split($t) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $key = mb_strtolower($line);   // 大文字小文字/全半角差を吸収したいならここで正規化を追加
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $lines[] = $line;
                }
            }
        }
        $noteMerged = implode("\n", $lines);

        // 表示用レコード
        $record = [
            'name'      => optional($req->user)->name,
            'clock_in'  => $hm($pick($req->new_clock_in,  $req->clock_in,  optional($a)->clock_in_at)),
            'clock_out' => $hm($pick($req->new_clock_out, $req->clock_out, optional($a)->clock_out_at)),
            'note'      => $noteMerged,   // ← 重複排除済み
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
