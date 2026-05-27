<?php

namespace Modules\Promotion\Handlers\Conditions;

use Modules\Promotion\Contracts\ConditionHandlerInterface;
use Modules\Promotion\DTOs\ConditionCheckResult;
use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\CouponCondition;
use Modules\Shared\Enums\FailureReason;

class MinAmountConditionHandler implements ConditionHandlerInterface
{
    public function conditionType(): string
    {
        return 'cart.min_amount';
    }

    public function evaluate(CouponCondition $condition, ConditionContext $context): ConditionCheckResult
    {
        $minAmount = (int) $condition->value;

        if ($context->checkout->orderTotal < $minAmount) {
            return ConditionCheckResult::failed(FailureReason::CouponConditionNotMatched, [
                'required_min_amount' => $minAmount,
                'order_total' => $context->checkout->orderTotal,
            ]);
        }

        return ConditionCheckResult::passed();
    }
}
