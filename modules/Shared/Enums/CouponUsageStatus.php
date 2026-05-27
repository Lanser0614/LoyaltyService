<?php

namespace Modules\Shared\Enums;

enum CouponUsageStatus: string
{
    case Reserved = 'reserved';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
