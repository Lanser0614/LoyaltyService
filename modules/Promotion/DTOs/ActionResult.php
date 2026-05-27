<?php

namespace Modules\Promotion\DTOs;

use Modules\Shared\DTOs\FreeItemDTO;
use Modules\Shared\Enums\IncentiveType;

class ActionResult
{
    /**
     * @param array<int, FreeItemDTO> $freeItems
     * @param array<int, IncentiveType> $incentiveTypes
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public readonly int $discountAmount,
        public readonly array $freeItems = [],
        public readonly array $incentiveTypes = [],
        public readonly array $snapshot = [],
    ) {
    }

    public function merge(self $other): self
    {
        return new self(
            discountAmount: $this->discountAmount + $other->discountAmount,
            freeItems: [...$this->freeItems, ...$other->freeItems],
            incentiveTypes: array_values(array_unique([...$this->incentiveTypes, ...$other->incentiveTypes], SORT_REGULAR)),
            snapshot: [...$this->snapshot, ...$other->snapshot],
        );
    }
}
