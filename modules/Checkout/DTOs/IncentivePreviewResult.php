<?php

namespace Modules\Checkout\DTOs;

use Modules\Shared\DTOs\FreeItemDTO;
use Modules\Shared\Enums\FailureReason;

class IncentivePreviewResult
{
    /**
     * @param array<int, FreeItemDTO> $freeItems
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly int $discountAmount,
        public readonly array $freeItems,
        public readonly int $finalAmount,
        public readonly int $paymentAmount,
        public readonly ?FailureReason $failureReason = null,
        public readonly array $snapshot = [],
    ) {
    }

    public static function rejected(FailureReason $reason): self
    {
        return new self(false, 0, [], 0, 0, $reason);
    }

    public function toArray(): array
    {
        if (! $this->allowed) {
            return [
                'allowed' => false,
                'failure_reason' => $this->failureReason?->value,
            ];
        }

        return [
            'allowed' => true,
            'discount_amount' => $this->discountAmount,
            'free_items' => array_map(fn (FreeItemDTO $item) => $item->toArray(), $this->freeItems),
            'final_amount' => $this->finalAmount,
            'payment_amount' => $this->paymentAmount,
        ];
    }
}
