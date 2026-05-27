<?php

namespace Modules\Promotion\Handlers\Actions;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;
use Modules\Shared\Enums\IncentiveType;

class PercentDiscountHandler implements CouponActionHandlerInterface
{
    public function actionType(): string
    {
        return 'percent';
    }

    public function resolve(CouponAction $action, ActionContext $context): ActionResult
    {
        $rawDiscount = (int) floor($context->checkout->orderTotal * ((int) $action->value / 100));
        $discount = $action->max_discount_amount !== null
            ? min($rawDiscount, (int) $action->max_discount_amount)
            : $rawDiscount;

        return new ActionResult(
            discountAmount: max(0, $discount),
            incentiveTypes: [IncentiveType::PromotionDiscount],
            snapshot: [
                'percent' => [
                    'value' => (int) $action->value,
                    'discount_amount' => max(0, $discount),
                ],
            ],
        );
    }
}
