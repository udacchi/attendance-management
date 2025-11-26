<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class AttendanceDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'total_work_minutes',
        'total_break_minutes',
        'note',
    ];

    protected $casts = [
        'work_date'      => 'date',
        'clock_in_at'    => 'datetime',
        'clock_out_at'   => 'datetime',
        'break1_start_at' => 'datetime',
        'break1_end_at'  => 'datetime',
        'break2_start_at' => 'datetime',
        'break2_end_at'  => 'datetime',
    ];

    // === ここから 0時跨ぎ対応付きの合計再計算メソッド ===
    public function recalcTotals(): void
    {
        // 出勤 or 退勤がない場合は計算しない
        if (!$this->clock_in_at || !$this->clock_out_at) {
            $this->total_work_minutes  = null;
            $this->total_break_minutes = null;
            return;
        }

        // コピーを作成（元の値を壊さないため）
        $clockIn  = $this->clock_in_at->copy();
        $clockOut = $this->clock_out_at->copy();

        // ★ ここが今回の追加ポイント：退勤 < 出勤 の場合は翌日にずらす
        if ($clockOut->lessThan($clockIn)) {
            $clockOut->addDay();   // 退勤を +1日
        }

        // まずは単純な勤務時間（休憩抜き）を分で計算
        $workMinutesRaw = $clockIn->diffInMinutes($clockOut);

        // 休憩時間の合計（既に計算済みならそのまま、BreakPeriod から計算しているならそちらを利用）
        // ここでは「すでに total_break_minutes に入っている」想定にしています。
        $breakMinutes = $this->total_break_minutes ?? 0;

        // 最終的な勤務時間（休憩を引く）
        $workMinutes = max(0, $workMinutesRaw - $breakMinutes);

        $this->total_work_minutes = $workMinutes;
    }

    // --- Relations ---
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breakPeriods()
    {
        return $this->hasMany(BreakPeriod::class, 'attendance_day_id');
    }

    public function breaks()
    {
        return $this->breakPeriods();
    }

    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class, 'attendance_day_id');
    }

    /* ===== 集計（分） ===== */

    /**
     * 休憩合計（分）
     * 列があれば列、無ければリレーションから算出（カラム名の揺れにも対応）
     */
    public function getBreakMinutesAttribute(): int
    {
        if (!is_null($this->total_break_minutes)) {
            return (int) $this->total_break_minutes;
        }

        // breaks が未ロードなら取得
        $breaks = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();

        $sum = 0;
        foreach ($breaks as $bp) {
            // 列名の揺れ対応
            $start = $bp->started_at ?? $bp->start_at ?? $bp->begin_at ?? null;
            $end   = $bp->ended_at   ?? $bp->end_at   ?? $bp->finish_at ?? null;
            if ($start && $end) {
                $sum += Carbon::parse($end)->diffInMinutes(Carbon::parse($start));
            }
        }
        return $sum;
    }

    /**
     * 勤務合計（分）＝(退勤 - 出勤) - 休憩
     * 列があれば列を優先
     */
    public function getWorkMinutesAttribute(): ?int
    {
        // 両方の打刻が揃っているなら、0時跨ぎを考慮して計算を優先
        if ($this->clock_in_at && $this->clock_out_at) {
            $tz  = config('app.timezone', 'Asia/Tokyo');
            $in  = Carbon::parse($this->clock_in_at)->timezone($tz);
            $out = Carbon::parse($this->clock_out_at)->timezone($tz);

            // ★ 退勤 < 出勤 のときは「翌日の退勤」とみなす（0時跨ぎ対応）
            if ($out->lt($in)) {
                $out->addDay();
            }

            $gross = $out->diffInMinutes($in); // 必ず0以上になる

            // 休憩分を引く
            return max(0, $gross - $this->break_minutes);
        }

        // 片方未打刻なら保存済みの値（あれば）を返す／なければ null
        if (!is_null($this->total_work_minutes)) {
            return max(0, (int) $this->total_work_minutes);
        }

        return null;
    }


    /* ===== 表示用（HH:MM） ===== */

    /** 休憩合計（表示用） */
    public function getBreakTotalAttribute(): ?string
    {
        $m = $this->break_minutes;
        if ($m <= 0) return null; // 0分は null → Blade 側の ?? '-' に任せる
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /** 勤務合計（表示用） */
    public function getWorkTotalAttribute(): ?string
    {
        if ($this->work_minutes === null) return null; // 未打刻時は null
        $m = $this->work_minutes;
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /* ===== 既存（残してOK） ===== */

    public function getTotalWorkHoursAttribute(): ?string
    {
        return is_null($this->total_work_minutes)
            ? null
            : sprintf('%d:%02d', intdiv($this->total_work_minutes, 60), $this->total_work_minutes % 60);
    }

    public function getTotalBreakHoursAttribute(): ?string
    {
        return is_null($this->total_break_minutes)
            ? null
            : sprintf('%d:%02d', intdiv($this->total_break_minutes, 60), $this->total_break_minutes % 60);
    }
}
