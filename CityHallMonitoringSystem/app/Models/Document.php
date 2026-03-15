<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'encoded_by_id',
        'type_of_document',
        'document_code',
        'document_number',
        'pay_claimant',
        'contact_number',
        'name_of_business',
        'reason',
        'particular',
        'amount',
        'routed_department_id',
        'status',
        'remarks',
        'date_out',
        'inactive_alerted_at',
        'inactive_read_at',
        'inactive_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'date_out' => 'date',
        'amount' => 'decimal:2',
        'inactive_alerted_at' => 'datetime',
        'inactive_read_at' => 'datetime',
    ];

    public function encodedBy()
    {
        return $this->belongsTo(User::class, 'encoded_by_id');
    }

    public function routedDepartment()
    {
        return $this->belongsTo(Department::class, 'routed_department_id');
    }

    public function routingHistories()
    {
        return $this->hasMany(DocumentRoutingHistory::class);
    }
}
