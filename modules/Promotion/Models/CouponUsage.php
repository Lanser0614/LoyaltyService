<?php

namespace Modules\Promotion\Models;

use Modules\CustomerWallet\Models\CustomerCoupon;
use Modules\Shared\Enums\CouponUsageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'customer_coupon_id',
        'customer_id',
        'order_id',
        'status',
        'discount_amount',
        'free_items_snapshot',
        'reservation_key',
        'validation_snapshot_id',
        'applied_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => CouponUsageStatus::class,
        'discount_amount' => 'integer',
        'free_items_snapshot' => 'array',
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customerCoupon(): BelongsTo
    {
        return $this->belongsTo(CustomerCoupon::class);
    }
}
