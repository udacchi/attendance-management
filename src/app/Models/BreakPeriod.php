<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BreakPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_day_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class);
    }
}
