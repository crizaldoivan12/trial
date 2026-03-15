<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'requested_password',
        'requested_password_length',
        'status',
        'approved_by_id',
        'approved_at',
        'rejected_by_id',
        'rejected_at',
        'read_by_user_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'read_by_user_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }
}
