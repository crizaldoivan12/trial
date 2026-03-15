<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled',
        'frequency',
        'email',
        'last_generated_at',
        'last_report_type',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_generated_at' => 'datetime',
    ];
}

