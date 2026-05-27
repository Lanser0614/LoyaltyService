<?php

namespace Modules\Promotion\Services;

use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Registries\ConditionHandlerRegistry;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class ConditionEvaluator
{
    public function __construct(private readonly ConditionHandlerRegistry $handlers)
    {
    }

    public function assertMatches(Coupon $coupon, ConditionContext $context): void
    {
        foreach ($coupon->conditions as $condition) {
            $result = $this->handlers
                ->resolve($condition->condition_type)
                ->evaluate($condition, $context);

            if (! $result->passed) {
                throw new IncentiveRejectedException($result->failureReason);
            }
        }
    }
}
