<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRoutingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'from_department_id',
        'to_department_id',
        'routed_by_id',
        'status',
        'remarks',
        'action_taken',
        'action_at',
        'reviewed_at',
        'signed_at',
        'action_by_id',
        'routed_at',
    ];

    protected $casts = [
        'routed_at' => 'datetime',
        'action_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function routedBy()
    {
        return $this->belongsTo(User::class, 'routed_by_id');
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by_id');
    }
}
