<?php

namespace Modules\CustomerWallet\Models;

use Modules\Promotion\Models\Coupon;
use Modules\Shared\Enums\CustomerCouponStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'code',
        'campaign_key',
        'status',
        'issued_reason',
        'issued_by_type',
        'issued_by_id',
        'source_event_id',
        'starts_at',
        'expires_at',
        'used_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'status' => CustomerCouponStatus::class,
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
