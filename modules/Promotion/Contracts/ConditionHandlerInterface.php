<?php

namespace Modules\Promotion\Contracts;

use Modules\Promotion\DTOs\ConditionCheckResult;
use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\CouponCondition;

interface ConditionHandlerInterface
{
    public function conditionType(): string;

    public function evaluate(CouponCondition $condition, ConditionContext $context): ConditionCheckResult;
}
