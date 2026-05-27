<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'action_type',
        'value',
        'max_discount_amount',
        'product_iiko_id',
        'combo_iiko_id',
        'quantity',
        'price_override',
        'metadata',
    ];

    protected $casts = [
        'value' => 'integer',
        'max_discount_amount' => 'integer',
        'quantity' => 'integer',
        'price_override' => 'integer',
        'metadata' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
