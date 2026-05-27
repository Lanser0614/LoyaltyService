<?php

namespace Modules\IncentivePolicy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentiveStackingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_incentive_type',
        'secondary_incentive_type',
        'is_allowed',
        'priority',
        'failure_reason',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'is_allowed' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
