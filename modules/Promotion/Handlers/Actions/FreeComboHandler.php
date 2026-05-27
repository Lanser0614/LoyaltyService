<?php

namespace Modules\Promotion\Handlers\Actions;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;
use Modules\Shared\Enums\IncentiveType;

class FreeComboHandler implements CouponActionHandlerInterface
{
    public function actionType(): string
    {
        return 'free_combo';
    }

    public function resolve(CouponAction $action, ActionContext $context): ActionResult
    {
        return new ActionResult(
            discountAmount: 0,
            incentiveTypes: [IncentiveType::FreeCombo],
            snapshot: [
                'free_combo' => [
                    'combo_iiko_id' => $action->combo_iiko_id,
                    'quantity' => max(1, (int) ($action->quantity ?? 1)),
                    'price' => (int) ($action->price_override ?? 0),
                    'source' => 'loyalty_promotion',
                ],
            ],
        );
    }
}
