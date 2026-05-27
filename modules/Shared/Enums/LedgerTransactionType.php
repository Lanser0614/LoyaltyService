<?php

namespace Modules\Shared\Enums;

enum LedgerTransactionType: string
{
    case Accrual = 'accrual';
    case Redemption = 'redemption';
    case Reservation = 'reservation';
    case Release = 'release';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case Expire = 'expire';
}
