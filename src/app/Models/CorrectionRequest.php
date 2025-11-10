<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorrectionRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'attendance_day_id',
        'requested_by',
        'reason',
        'proposed_clock_in_at',
        'proposed_clock_out_at',
        'proposed_note',
        'status',
    ];

    protected $casts = [
        'target_at'     => 'datetime',
        'clock_in'      => 'datetime',
        'clock_out'     => 'datetime',
        'break1_start'  => 'datetime',
        'break1_end'    => 'datetime',
        'break2_start'  => 'datetime',
        'break2_end'    => 'datetime',
    ];

    // --- Relations ---
    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function logs()
    {
        return $this->hasMany(CorrectionLog::class);
    }

    // スコープ例：ステータス絞り
    public function scopeStatus($q, string $status)
    {
        return $q->where('status', $status);
    }
}
