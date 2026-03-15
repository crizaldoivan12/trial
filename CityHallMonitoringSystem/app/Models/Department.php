<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'office',
        'department_head',
        'is_active',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'routed_department_id');
    }

    public function routingHistoryFrom()
    {
        return $this->hasMany(DocumentRoutingHistory::class, 'from_department_id');
    }

    public function routingHistoryTo()
    {
        return $this->hasMany(DocumentRoutingHistory::class, 'to_department_id');
    }
}
