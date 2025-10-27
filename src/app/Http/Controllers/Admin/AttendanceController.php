<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceDay;
use App\Models\User;
use App\Models\CorrectionLog;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        // ▼ 1) 表示する基準日（?date=YYYY-MM-DD / ?month=YYYY-MM / データ最新日）
        $latest = AttendanceDay::max('work_date');
        if ($request->filled('month')) {
            $date = Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth();
        } elseif ($request->filled('date')) {
            $date = Carbon::parse($request->query('date'), $tz)->startOfDay();
        } elseif ($latest) {
            $date = Carbon::parse($latest, $tz)->startOfDay(); // ← 明示
        } else {
            $date = Carbon::now($tz)->startOfDay();
        }

        // 既存の絞り込み（必要ならそのまま維持）
        $userId   = $request->query('user_id');
        $dateFrom = $request->query('from');
        $dateTo   = $request->query('to');

        // ▼ 2) ページネーション付きの全件一覧（そのまま）
        $attendances = AttendanceDay::query()
            ->when($userId,  fn($q) => $q->where('user_id', $userId))
            ->when($dateFrom, fn($q) => $q->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('work_date', '<=', $dateTo))
            ->with('user:id,name')
            ->orderByDesc('work_date')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // ▼ 3) その1日分を Blade の $records 形式に整形
        $fmtTime = function ($v) {
            if (empty($v)) return '–';
            if ($v instanceof \DateTimeInterface) return $v->format('H:i');
            if (is_string($v) && preg_match('/^\d{1,2}:\d{2}/', $v)) return substr($v, 0, 5);
            try {
                return Carbon::parse($v)->format('H:i');
            } catch (\Throwable $e) {
                return '–';
            }
        };

        $minutesToHMM = function ($mins) {
            if ($mins === null) return '–';
            $h = intdiv((int)$mins, 60);
            $m = ((int)$mins) % 60;
            return sprintf('%d:%02d', $h, $m);
        };

        $dayItems = AttendanceDay::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereDate('work_date', $date->toDateString())
            ->with('user:id,name')
            ->orderBy('user_id')
            ->get();

        $records = $dayItems->map(function ($a) use ($fmtTime, $minutesToHMM) {
            return [
                'id'          => $a->id,
                'user_id'     => $a->user_id,
                'name'        => optional($a->user)->name ?? '—',
                'clock_in'    => $fmtTime($a->clock_in_at),
                'clock_out'   => $fmtTime($a->clock_out_at),
                // ↓ これらのカラムが無い場合は後述の「代替計算」に差し替え
                'break_total' => $minutesToHMM($a->total_break_minutes),
                'work_total'  => $minutesToHMM($a->total_work_minutes),
            ];
        })->values();

        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        return view('admin.attendance.list', compact(
            'date',
            'records',                 // ★ 追加
            'attendances',
            'users',
            'userId',
            'dateFrom',
            'dateTo'
        ));
    }

    public function show(AttendanceDay $attendanceDay)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        $attendanceDay->load(['user', 'breakPeriods']);

        $date = $attendanceDay->work_date
            ? Carbon::parse($attendanceDay->work_date, $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        // 休憩合計（秒）
        $breakSeconds = 0;
        foreach ($attendanceDay->breakPeriods ?? [] as $bp) {
            $start = $bp->break_start_at ? Carbon::parse($bp->break_start_at, $tz) : null;
            $end   = $bp->break_end_at   ? Carbon::parse($bp->break_end_at, $tz)   : null;
            if ($start && $end && $end->greaterThan($start)) {
                $breakSeconds += $end->diffInSeconds($start);
            }
        }

        $clockIn  = $attendanceDay->clock_in_at  ? Carbon::parse($attendanceDay->clock_in_at, $tz)  : null;
        $clockOut = $attendanceDay->clock_out_at ? Carbon::parse($attendanceDay->clock_out_at, $tz) : null;

        $workSeconds = 0;
        if ($clockIn && $clockOut && $clockOut->greaterThan($clockIn)) {
            $workSeconds = max(0, $clockOut->diffInSeconds($clockIn) - $breakSeconds);
        }

        $fmtHM = function (?int $seconds) {
            if ($seconds === null) return '–';
            $minutes = intdiv($seconds, 60); // 秒は切り捨て
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return sprintf('%02d:%02d', $h, $m);
        };

        $record = [
            'user_id'     => optional($attendanceDay->user)->id,
            'name'        => optional($attendanceDay->user)->name ?? '-',
            'date'        => $date->toDateString(),
            'clock_in'    => $clockIn  ? $clockIn->format('H:i')  : '–',
            'clock_out'   => $clockOut ? $clockOut->format('H:i') : '–',
            'break_total' => $fmtHM($breakSeconds),
            'work_total'  => $fmtHM($workSeconds),
            'raw'         => $attendanceDay,
        ];

        return view('admin.attendance.detail', [
            'date'   => $date,
            'user'   => $attendanceDay->user,
            'record' => $record,
        ]);
    }

    // 編集フォーム（user+date ベース）
    public function editByUserDate(Request $request, User $user)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        // その日のレコードが無ければ作る（管理者の直接修正対象を必ず用意）
        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            [] // 初期値は空でOK（打刻なし状態）
        );

        $attendanceDay->load('breakPeriods'); // 必要なら

        return view('admin.attendance.edit', [
            'user'          => $user,
            'date'          => $date,
            'attendanceDay' => $attendanceDay,
        ]);
    }

    // 更新処理（管理者が直接反映）
    public function updateByUserDate(Request $request, User $user)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $attendanceDay = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            []
        );

        // 入力バリデーション（必要に応じて拡張）
        $data = $request->validate([
            'clock_in_at'            => ['nullable', 'date'],
            'clock_out_at'           => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            // 休憩を行単位で受ける場合（配列）
            'breaks'                 => ['array'],
            'breaks.*.start'         => ['nullable', 'date'],
            'breaks.*.end'           => ['nullable', 'date', 'after:breaks.*.start'],
            'note'                   => ['nullable', 'string', 'max:1000'], // 管理者メモ等
        ]);

        DB::transaction(function () use ($data, $attendanceDay, $tz, $user, $date) {
            // 監査用：変更前スナップショット
            $before = [
                'clock_in_at'  => $attendanceDay->clock_in_at,
                'clock_out_at' => $attendanceDay->clock_out_at,
                'breaks'       => $attendanceDay->breakPeriods?->map(fn($b) => [
                    'start' => $b->break_start_at,
                    'end' => $b->break_end_at
                ])->values()->all() ?? [],
            ];

            // 勤怠本体の更新（秒はそのまま保存、表示時に切り捨てフォーマット）
            $attendanceDay->clock_in_at  = $data['clock_in_at']  ?? null;
            $attendanceDay->clock_out_at = $data['clock_out_at'] ?? null;
            $attendanceDay->save();

            // 休憩明細を差し替え（必要な場合のみ）
            if (isset($data['breaks'])) {
                // いったん全消しして入れ直し（更新が面倒ならこの方が安全）
                $attendanceDay->breakPeriods()->delete();
                foreach ($data['breaks'] as $row) {
                    if (empty($row['start']) || empty($row['end'])) continue;
                    $attendanceDay->breakPeriods()->create([
                        'break_start_at' => Carbon::parse($row['start'], $tz),
                        'break_end_at'   => Carbon::parse($row['end'], $tz),
                    ]);
                }
            }

            // 必要なら合計分を再計算して保存（列がある場合）
            // break_total / work_total を“分”で保持しているならここで再計算
            $attendanceDay->load('breakPeriods');
            $breakSeconds = 0;
            foreach ($attendanceDay->breakPeriods as $bp) {
                $s = $bp->break_start_at ? Carbon::parse($bp->break_start_at, $tz) : null;
                $e = $bp->break_end_at   ? Carbon::parse($bp->break_end_at, $tz)   : null;
                if ($s && $e && $e->gt($s)) $breakSeconds += $e->diffInSeconds($s);
            }

            $clockIn  = $attendanceDay->clock_in_at  ? Carbon::parse($attendanceDay->clock_in_at, $tz)  : null;
            $clockOut = $attendanceDay->clock_out_at ? Carbon::parse($attendanceDay->clock_out_at, $tz) : null;

            $workSeconds = 0;
            if ($clockIn && $clockOut && $clockOut->gt($clockIn)) {
                $workSeconds = max(0, $clockOut->diffInSeconds($clockIn) - $breakSeconds);
            }

            // “秒は切り捨て” → 分で保存する場合
            if ($attendanceDay->isFillable('total_break_minutes') && $attendanceDay->isFillable('total_work_minutes')) {
                $attendanceDay->total_break_minutes = intdiv($breakSeconds, 60);
                $attendanceDay->total_work_minutes  = intdiv($workSeconds, 60);
                $attendanceDay->save();
            }

            // 監査ログ（管理者が直接修正した証跡）
            if (class_exists(CorrectionLog::class)) {
                CorrectionLog::create([
                    'acted_by_admin_id' => Auth::guard('admin')->id(),
                    'user_id'           => $user->id,
                    'work_date'         => $date->toDateString(),
                    'before_json'       => json_encode($before, JSON_UNESCAPED_UNICODE),
                    'after_json'        => json_encode([
                        'clock_in_at'  => $attendanceDay->clock_in_at,
                        'clock_out_at' => $attendanceDay->clock_out_at,
                        'breaks'       => $attendanceDay->breakPeriods?->map(fn($b) => [
                            'start' => $b->break_start_at,
                            'end' => $b->break_end_at
                        ])->values()->all() ?? [],
                    ], JSON_UNESCAPED_UNICODE),
                    'note'              => $data['note'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.attendance.detail', ['attendanceDay' => $attendanceDay->id])
            ->with('status', '管理者による修正を反映しました。');
    }

    public function staff(Request $request, int $id)
    {
        $tz    = config('app.timezone', 'Asia/Tokyo');
        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $user = User::findOrFail($id);

        // 該当月の勤怠（必要に応じて with(...) で休憩なども）
        $days = AttendanceDay::where('user_id', $id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        return view('admin.attendance.staff', [
            'user'  => $user,
            'month' => $month,
            'from'  => $from,
            'to'    => $to,
            'days'  => $days,
        ]);
    }

    public function staffCsv(\Illuminate\Http\Request $request, int $id): StreamedResponse
    {
        $tz    = config('app.timezone', 'Asia/Tokyo');
        $month = $request->query('month')
            ? \Carbon\Carbon::createFromFormat('Y-m', $request->query('month'), $tz)->startOfMonth()
            : \Carbon\Carbon::now($tz)->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $user = \App\Models\User::findOrFail($id);

        // 月内の勤怠を取得（必要に応じて列名はあなたのスキーマに合わせて調整）
        $days = \App\Models\AttendanceDay::where('user_id', $id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $month->format('Y-m'));

        return response()->streamDownload(function () use ($days) {
            $out = fopen('php://output', 'w');
            // ヘッダ行（UTF-8 / ExcelならS-JISに変換する場合は iconv を使用）
            fputcsv($out, ['date', 'clock_in', 'clock_out', 'break_total', 'work_total']);

            foreach ($days as $d) {
                // あなたのカラム名に合わせて整形
                $date        = $d->work_date;
                $clockIn     = optional($d->clock_in_at)->format('H:i');
                $clockOut    = optional($d->clock_out_at)->format('H:i');
                $breakTotal  = $d->break_total_text ?? ''; // アクセサが無ければ自前で整形
                $workTotal   = $d->work_total_text  ?? '';

                fputcsv($out, [
                    $date,
                    $clockIn ?: '',
                    $clockOut ?: '',
                    $breakTotal,
                    $workTotal,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
