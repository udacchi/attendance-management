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
        'proposed_clock_in_at' => 'datetime',
        'proposed_clock_out_at' => 'datetime',
    ];

    // --- Relations ---
    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    public function requester()
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
