<?php

namespace Modules\Promotion\Handlers\Actions;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;
use Modules\Shared\DTOs\FreeItemDTO;
use Modules\Shared\Enums\IncentiveType;

class FreeProductHandler implements CouponActionHandlerInterface
{
    public function actionType(): string
    {
        return 'free_product';
    }

    public function resolve(CouponAction $action, ActionContext $context): ActionResult
    {
        $freeItem = new FreeItemDTO(
            iikoProductId: (string) $action->product_iiko_id,
            quantity: max(1, (int) ($action->quantity ?? 1)),
            price: (int) ($action->price_override ?? 0),
        );

        return new ActionResult(
            discountAmount: 0,
            freeItems: [$freeItem],
            incentiveTypes: [IncentiveType::FreeProduct],
            snapshot: [
                'free_product' => $freeItem->toArray(),
            ],
        );
    }
}
