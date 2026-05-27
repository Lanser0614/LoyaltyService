<?php

namespace Modules\Promotion\DTOs;

use Modules\Checkout\DTOs\CheckoutIncentiveRequest;

class ActionContext
{
    public function __construct(public readonly CheckoutIncentiveRequest $checkout)
    {
    }
}
