<?php

namespace Modules\Promotion\Handlers\Actions;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;
use Modules\Shared\Enums\IncentiveType;

class FreeDeliveryHandler implements CouponActionHandlerInterface
{
    public function actionType(): string
    {
        return 'free_delivery';
    }

    public function resolve(CouponAction $action, ActionContext $context): ActionResult
    {
        return new ActionResult(
            discountAmount: max(0, $context->checkout->deliveryFee),
            incentiveTypes: [IncentiveType::FreeDelivery],
            snapshot: [
                'free_delivery' => [
                    'discount_amount' => max(0, $context->checkout->deliveryFee),
                ],
            ],
        );
    }
}
