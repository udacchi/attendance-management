<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
        $tz = config('app.timezone', 'Asia/Tokyo');

        DB::transaction(function () use ($attendance_correct_request_id, $tz) {
            /** @var \App\Models\CorrectionRequest $req */
            $req = \App\Models\CorrectionRequest::with('attendanceDay')
                ->lockForUpdate()
                ->findOrFail($attendance_correct_request_id);

            if ($req->status === 'approved') {
                return; // 二重承認防止
            }

            // ------- 対象ユーザー／日付の特定（列差異に頑丈に） -------
            $userId = $req->requested_user_id
                ?? $req->requested_by
                ?? $req->user_id
                ?? optional($req->attendanceDay)->user_id;

            if (!$userId) {
                throw new \RuntimeException('requested_user_id/requested_by/user_id が見つかりません');
            }

            $workDate = null;
            // 明示列 or attendanceDay から日付を引く
            foreach (['work_date', 'target_date', 'date', 'target_at'] as $col) {
                if (!empty($req->{$col})) {
                    $workDate = \Carbon\Carbon::parse($req->{$col}, $tz)->toDateString();
                    break;
                }
            }
            if (!$workDate && $req->attendanceDay && $req->attendanceDay->work_date) {
                $workDate = \Carbon\Carbon::parse($req->attendanceDay->work_date, $tz)->toDateString();
            }
            if (!$workDate) $workDate = now($tz)->toDateString();

            /** @var \App\Models\AttendanceDay $a */
            $a = \App\Models\AttendanceDay::firstOrCreate([
                'user_id'   => $userId,
                'work_date' => $workDate,
            ]);

            // ------- 承認データの取り出し（payload → proposed_* → 旧new_* → 旧カラム） -------
            $payload = $req->payload;
            if (!is_array($payload)) {
                $payload = json_decode($payload ?? '', true);
                if (!is_array($payload)) $payload = [];
            }

            $pick = function (...$candidates) {
                foreach ($candidates as $v) {
                    if ($v === null) continue;
                    $s = is_string($v) ? trim($v) : $v;
                    if ($s !== '' && $s !== '--:--') return $v;
                }
                return null;
            };

            $clockInSrc  = $pick($payload['clock_in'] ?? null,  $req->proposed_clock_in_at  ?? null, $req->new_clock_in  ?? null,  $req->clock_in  ?? null);
            $clockOutSrc = $pick($payload['clock_out'] ?? null, $req->proposed_clock_out_at ?? null, $req->new_clock_out ?? null, $req->clock_out ?? null);
            $noteSrc     = $pick($payload['note'] ?? null,      $req->proposed_note ?? null, $req->reason ?? null);

            $toDT = function ($v) use ($tz, $workDate) {
                if (!$v) return null;
                if (is_string($v) && preg_match('/^\d{1,2}:\d{2}$/', trim($v))) {
                    return \Carbon\Carbon::createFromFormat('Y-m-d H:i', $workDate . ' ' . trim($v), $tz)->second(0);
                }
                try {
                    return \Carbon\Carbon::parse($v, $tz);
                } catch (\Throwable) {
                    return null;
                }
            };

            $in  = $toDT($clockInSrc);
            $out = $toDT($clockOutSrc);

            // ------- 休憩の取り出し（payload 最優先、なければ現状の break 明細を再利用） -------
            $relName = method_exists($a, 'breakPeriods') ? 'breakPeriods' : (method_exists($a, 'breaks') ? 'breaks' : null);
            $tbl = 'break_periods';
            if ($relName) {
                $a->load($relName);
                if ($a->$relName->first()) {
                    $tbl = $a->$relName->first()->getTable();
                } elseif (\Illuminate\Support\Facades\Schema::hasTable('breaks')) {
                    $tbl = 'breaks';
                }
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('breaks')) {
                $tbl = 'breaks';
            }
            $colStart = \Illuminate\Support\Facades\Schema::hasColumn($tbl, 'break_start_at') ? 'break_start_at'
                : (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'started_at') ? 'started_at'
                    : (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'start_at')   ? 'start_at' : null));
            $colEnd   = \Illuminate\Support\Facades\Schema::hasColumn($tbl, 'break_end_at') ? 'break_end_at'
                : (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'ended_at') ? 'ended_at'
                    : (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'end_at')   ? 'end_at'   : null));

            // 1) payload から breaks を復元
            $normBreaks = [];
            if (!empty($payload['breaks']) && is_array($payload['breaks'])) {
                foreach ($payload['breaks'] as $b) {
                    $sd = $toDT($b['start'] ?? null);
                    $ed = $toDT($b['end']   ?? null);
                    if ($sd && $ed) $normBreaks[] = [$sd, $ed];
                }
            }
            // 2) payload に無ければ、既存明細をそのまま読み替え
            if (!$normBreaks && $relName && $colStart && $colEnd) {
                foreach ($a->$relName as $bp) {
                    $sd = $bp->{$colStart} ? \Carbon\Carbon::parse($bp->{$colStart}, $tz) : null;
                    $ed = $bp->{$colEnd}   ? \Carbon\Carbon::parse($bp->{$colEnd},   $tz) : null;
                    if ($sd && $ed) $normBreaks[] = [$sd, $ed];
                }
            }

            // ------- 勤務スパン外の休憩はクリップ（退勤以降は保存しない） -------
            $spanIn  = $in;
            $spanOut = $out ? $out->copy() : null;
            if ($spanIn && $spanOut && $spanOut->lt($spanIn)) $spanOut->addDay(); // 跨日

            if ($spanIn && $spanOut) {
                $filtered = [];
                foreach ($normBreaks as [$s, $e]) {
                    if ($e->lt($s)) $e = $e->copy()->addDay(); // 念のため
                    $s2 = $s->max($spanIn);
                    $e2 = $e->min($spanOut);
                    if ($e2->gt($s2)) $filtered[] = [$s2, $e2];
                }
                $normBreaks = $filtered;
            }

            // ------- 本体の確定書き込み -------
            $a->clock_in_at  = $in;
            $a->clock_out_at = $out;
            if (\Illuminate\Support\Facades\Schema::hasColumn($a->getTable(), 'note')) {
                $a->note = (string)($noteSrc ?? $a->note);
            }
            $a->save();

            // 休憩を差し替え
            if ($relName && $colStart && $colEnd) {
                $a->$relName()->delete();
                foreach ($normBreaks as [$s, $e]) {
                    $a->$relName()->create([$colStart => $s->copy(), $colEnd => $e->copy()]);
                }
            }

            // ------- 合計の再計算（列があれば更新） -------
            if ($relName) $a->load($relName);
            $breakSeconds = 0;
            if ($relName && $colStart && $colEnd) {
                foreach ($a->$relName as $bp) {
                    $s = $bp->{$colStart} ? \Carbon\Carbon::parse($bp->{$colStart}, $tz) : null;
                    $e = $bp->{$colEnd}   ? \Carbon\Carbon::parse($bp->{$colEnd},   $tz) : null;
                    if ($s && $e && $e->gt($s)) $breakSeconds += $e->diffInSeconds($s);
                }
            }
            $adjOut = $out ? $out->copy() : null;
            if ($in && $adjOut && $adjOut->lt($in)) $adjOut->addDay();
            $workSeconds = ($in && $adjOut) ? max(0, $adjOut->diffInSeconds($in) - $breakSeconds) : 0;

            if (\Illuminate\Support\Facades\Schema::hasColumn($a->getTable(), 'total_break_minutes')) {
                $a->total_break_minutes = intdiv($breakSeconds, 60);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn($a->getTable(), 'total_work_minutes')) {
                $a->total_work_minutes  = intdiv($workSeconds, 60);
            }
            if ($a->isDirty()) $a->save();

            // ------- リクエストを承認に更新 -------
            $req->status = 'approved';
            if (\Illuminate\Support\Facades\Schema::hasColumn($req->getTable(), 'approved_at')) $req->approved_at = now($tz);
            if (\Illuminate\Support\Facades\Schema::hasColumn($req->getTable(), 'approved_by')) $req->approved_by = Auth::guard('admin')->id();
            $req->save();

            // （任意）同一ユーザー×同一日付の他の pending を無効化する場合はここで処理
            // CorrectionRequest::where(...)->where('status','pending')->update(['status' => 'superseded']);
        });

        return redirect()
            ->route('stamp_correction_request.approve', ['attendance_correct_request_id' => $attendance_correct_request_id])
            ->with('success', '承認しました（勤怠へ反映済み）。');
    }
}
