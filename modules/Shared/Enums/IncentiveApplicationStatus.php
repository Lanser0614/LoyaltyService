<?php

namespace Modules\Shared\Enums;

enum IncentiveApplicationStatus: string
{
    case Reserved = 'reserved';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
}
