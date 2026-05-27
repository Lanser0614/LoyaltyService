<?php

namespace Modules\Promotion\DTOs;

use Modules\Checkout\DTOs\CheckoutIncentiveRequest;

class ConditionContext
{
    public function __construct(public readonly CheckoutIncentiveRequest $checkout)
    {
    }
}
