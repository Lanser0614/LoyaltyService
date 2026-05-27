<?php

namespace Modules\Shared\Enums;

enum LedgerTransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
