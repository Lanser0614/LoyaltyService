<?php

return [
    'max_total_discount_amount' => (int) env('LOYALTY_MAX_TOTAL_DISCOUNT_AMOUNT', 80000),
    'max_bellcoin_redeem_percent' => (int) env('LOYALTY_MAX_BELLCOIN_REDEEM_PERCENT', 100),
    'max_bellcoin_redeem_amount' => (int) env('LOYALTY_MAX_BELLCOIN_REDEEM_AMOUNT', 80000),
    'bellcoin_accrual_percent' => (float) env('LOYALTY_BELLCOIN_ACCRUAL_PERCENT', 2),
    'bellcoin_expiration_days' => (int) env('LOYALTY_BELLCOIN_EXPIRATION_DAYS', 365),
    'reservation_ttl_minutes' => (int) env('LOYALTY_RESERVATION_TTL_MINUTES', 5),
];
