<?php

namespace Modules\Shared\DTOs;

class FreeItemDTO
{
    public function __construct(
        public readonly string $iikoProductId,
        public readonly int $quantity,
        public readonly int $price = 0,
        public readonly string $source = 'loyalty_promotion',
    ) {
    }

    public function toArray(): array
    {
        return [
            'iiko_product_id' => $this->iikoProductId,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'source' => $this->source,
        ];
    }
}
