<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // --- Relations ---
    public function attendanceDays()
    {
        return $this->hasMany(AttendanceDay::class);
    }

    // 自分が出した修正申請
    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class, 'requested_by');
    }

    // 管理者として操作したログ
    public function correctionLogs()
    {
        return $this->hasMany(CorrectionLog::class, 'admin_id');
    }

    // 役割ヘルパ
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
