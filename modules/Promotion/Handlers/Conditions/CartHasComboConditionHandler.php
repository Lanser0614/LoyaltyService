<?php

namespace Modules\Promotion\Handlers\Conditions;

use Modules\Promotion\Contracts\ConditionHandlerInterface;
use Modules\Promotion\DTOs\ConditionCheckResult;
use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\CouponCondition;
use Modules\Shared\Enums\FailureReason;

class CartHasComboConditionHandler implements ConditionHandlerInterface
{
    public function conditionType(): string
    {
        return 'cart.has_combo';
    }

    public function evaluate(CouponCondition $condition, ConditionContext $context): ConditionCheckResult
    {
        foreach ($context->checkout->items as $item) {
            if ($item->comboIikoId !== null) {
                return ConditionCheckResult::passed();
            }
        }

        return ConditionCheckResult::failed(FailureReason::CouponConditionNotMatched);
    }
}
