<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    // --- Relations ---
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(BreakPeriod::class, 'attendance_day_id');
    }

    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class, 'attendance_day_id');
    }

    // 表示用アクセサ（例）
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
