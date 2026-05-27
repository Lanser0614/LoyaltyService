<?php

namespace Modules\Promotion\Contracts;

use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ActionResult;
use Modules\Promotion\Models\CouponAction;

interface CouponActionHandlerInterface
{
    public function actionType(): string;

    public function resolve(CouponAction $action, ActionContext $context): ActionResult;
}
