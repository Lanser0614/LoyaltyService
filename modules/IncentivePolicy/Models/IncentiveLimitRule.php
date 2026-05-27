<?php

namespace Modules\IncentivePolicy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentiveLimitRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_type',
        'value',
        'status',
    ];

    protected $casts = [
        'value' => 'integer',
    ];
}
