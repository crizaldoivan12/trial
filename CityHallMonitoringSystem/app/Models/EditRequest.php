<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'requested_by_user_id',
        'requested_to_user_id',
        'status',
        'remarks',
        'accepted_at',
        'expires_at',
        'read_by_requested_to_at',
        'read_by_requested_by_at',
        'read_by_admin_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'read_by_requested_to_at' => 'datetime',
        'read_by_requested_by_at' => 'datetime',
        'read_by_admin_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requestedTo()
    {
        return $this->belongsTo(User::class, 'requested_to_user_id');
    }
}
