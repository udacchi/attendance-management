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
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
    ];

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
        // 両方の打刻が揃っているなら、DBの total_work_minutes に関わらず計算を優先
        if ($this->clock_in_at && $this->clock_out_at) {
            $tz  = config('app.timezone', 'Asia/Tokyo');
            $in  = \Carbon\Carbon::parse($this->clock_in_at)->timezone($tz);
            $out = \Carbon\Carbon::parse($this->clock_out_at)->timezone($tz);

            $gross = $out->diffInMinutes($in, false); // 退勤-出勤
            if ($gross < 0) $gross = 0;

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
