<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'condition_type',
        'operator',
        'value',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
