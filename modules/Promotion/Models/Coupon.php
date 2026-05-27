<?php

namespace Modules\Promotion\Models;

use Modules\Shared\Enums\CouponKind;
use Modules\Shared\Enums\CouponStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'coupon_kind',
        'starts_at',
        'ends_at',
        'status',
        'usage_limit_total',
        'usage_limit_per_customer',
        'issue_limit_total',
        'issue_limit_per_customer',
        'issue_policy',
        'auto_apply',
        'visible_to_customer',
        'requires_code_input',
        'priority',
        'stackable',
        'is_stackable_with_bellcoin',
        'is_stackable_with_other_coupons',
        'issued_from_coupon_id',
        'issued_customer_id',
        'issued_customer_phone',
        'source_order_id',
        'source_event_id',
    ];

    protected $casts = [
        'coupon_kind' => CouponKind::class,
        'status' => CouponStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'issue_policy' => 'array',
        'auto_apply' => 'bool',
        'visible_to_customer' => 'bool',
        'requires_code_input' => 'bool',
        'stackable' => 'bool',
        'is_stackable_with_bellcoin' => 'bool',
        'is_stackable_with_other_coupons' => 'bool',
        'issued_customer_id' => 'integer',
        'source_order_id' => 'integer',
    ];

    public function conditions(): HasMany
    {
        return $this->hasMany(CouponCondition::class);
    }

    public function issueConditions(): HasMany
    {
        return $this->hasMany(CouponIssueCondition::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CouponAction::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
