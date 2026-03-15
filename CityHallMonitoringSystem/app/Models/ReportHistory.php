<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'format',
        'filters',
        'generated_by_id',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }
}

