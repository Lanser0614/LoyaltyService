<?php

namespace Modules\Checkout\Models;

use Modules\Promotion\Models\CouponUsage;
use Modules\Shared\Enums\IncentiveApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIncentiveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'incentive_application_id',
        'order_id',
        'customer_id',
        'coupon_usage_id',
        'bellcoin_transaction_id',
        'total_discount_amount',
        'incentives_snapshot',
        'status',
        'reserved_at',
        'applied_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => IncentiveApplicationStatus::class,
        'total_discount_amount' => 'integer',
        'incentives_snapshot' => 'array',
        'reserved_at' => 'datetime',
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function couponUsage(): BelongsTo
    {
        return $this->belongsTo(CouponUsage::class);
    }
}
