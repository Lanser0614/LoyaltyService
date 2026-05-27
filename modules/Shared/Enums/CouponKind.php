<?php

namespace Modules\Shared\Enums;

enum CouponKind: string
{
    case PublicCodeCoupon = 'public_code_coupon';
    case IssuedCustomerCoupon = 'issued_customer_coupon';
}
