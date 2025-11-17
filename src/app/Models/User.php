<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable implements MustVerifyEmail 
{
    use Notifiable, MustVerifyEmailTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // 'admin' or 'user' を想定
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

    // 自分が出した修正申請（FK: requested_by）
    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class, 'requested_by');
    }

    // 管理者として操作したログ（FK: admin_id）
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
