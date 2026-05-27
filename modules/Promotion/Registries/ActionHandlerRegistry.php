<?php

namespace Modules\Promotion\Registries;

use Modules\Promotion\Contracts\CouponActionHandlerInterface;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class ActionHandlerRegistry
{
    /** @var array<string, CouponActionHandlerInterface> */
    private array $handlers = [];

    public function register(string $type, CouponActionHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function resolve(string $type): CouponActionHandlerInterface
    {
        return $this->handlers[$type]
            ?? throw new IncentiveRejectedException(FailureReason::InvalidActionDefinition);
    }

    public function definitions(): array
    {
        return array_keys($this->handlers);
    }
}
