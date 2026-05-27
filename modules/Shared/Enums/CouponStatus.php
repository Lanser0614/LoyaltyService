<?php

namespace Modules\Shared\Enums;

enum CouponStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Archived = 'archived';
}
