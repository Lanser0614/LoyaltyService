<?php

namespace Modules\Promotion\Handlers\Actions;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;
use Modules\Shared\Enums\IncentiveType;

class FixedDiscountHandler implements CouponActionHandlerInterface
{
    public function actionType(): string
    {
        return 'fixed';
    }

    public function resolve(CouponAction $action, ActionContext $context): ActionResult
    {
        $discount = min((int) $action->value, $context->checkout->orderTotal);

        return new ActionResult(
            discountAmount: max(0, $discount),
            incentiveTypes: [IncentiveType::PromotionDiscount],
            snapshot: [
                'fixed' => [
                    'value' => (int) $action->value,
                    'discount_amount' => max(0, $discount),
                ],
            ],
        );
    }
}
