<?php

namespace Modules\Promotion\Handlers\Conditions;

use Modules\CustomerInsights\Contracts\CustomerSegmentProviderInterface;
use Modules\Promotion\Contracts\ConditionHandlerInterface;
use Modules\Promotion\DTOs\ConditionCheckResult;
use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\CouponCondition;
use Modules\Shared\Enums\FailureReason;

class CustomerSegmentConditionHandler implements ConditionHandlerInterface
{
    public function __construct(private readonly CustomerSegmentProviderInterface $segments)
    {
    }

    public function conditionType(): string
    {
        return 'customer.segment';
    }

    public function evaluate(CouponCondition $condition, ConditionContext $context): ConditionCheckResult
    {
        $segmentCode = (string) ($condition->payload['segment_code'] ?? $condition->value);

        if (! $this->segments->hasSegment($context->checkout->customerId, $segmentCode)) {
            return ConditionCheckResult::failed(FailureReason::CouponConditionNotMatched, [
                'segment_code' => $segmentCode,
            ]);
        }

        return ConditionCheckResult::passed();
    }
}
