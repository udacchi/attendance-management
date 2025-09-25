<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorrectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'correction_request_id',
        'admin_id',
        'action',
        'comment',
    ];

    // --- Relations ---
    public function correctionRequest()
    {
        return $this->belongsTo(CorrectionRequest::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
