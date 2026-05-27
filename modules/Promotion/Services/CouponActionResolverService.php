<?php

namespace Modules\Promotion\Services;

use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Registries\ActionHandlerRegistry;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class CouponActionResolverService
{
    public function __construct(private readonly ActionHandlerRegistry $handlers)
    {
    }

    public function resolve(Coupon $coupon, ActionContext $context): ActionResult
    {
        if ($coupon->actions->isEmpty()) {
            throw new IncentiveRejectedException(FailureReason::CouponActionNotFound);
        }

        $result = new ActionResult(0);

        foreach ($coupon->actions as $action) {
            $result = $result->merge(
                $this->handlers->resolve($action->action_type)->resolve($action, $context)
            );
        }

        return $result;
    }
}
