<?php

namespace Modules\Shared\Enums;

enum CustomerCouponStatus: string
{
    case Issued = 'issued';
    case Available = 'available';
    case Reserved = 'reserved';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
