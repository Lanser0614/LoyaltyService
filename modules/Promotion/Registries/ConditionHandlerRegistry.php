<?php

namespace Modules\Promotion\Registries;

use Modules\Promotion\Contracts\ConditionHandlerInterface;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class ConditionHandlerRegistry
{
    /** @var array<string, ConditionHandlerInterface> */
    private array $handlers = [];

    public function register(string $type, ConditionHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function resolve(string $type): ConditionHandlerInterface
    {
        return $this->handlers[$type]
            ?? throw new IncentiveRejectedException(FailureReason::InvalidConditionDefinition);
    }

    public function definitions(): array
    {
        return array_keys($this->handlers);
    }
}
