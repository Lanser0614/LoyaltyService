<?php

namespace Modules\Checkout\DTOs;

class CheckoutItemDTO
{
    public function __construct(
        public readonly string $iikoProductId,
        public readonly int $quantity,
        public readonly int $price,
        public readonly ?string $comboIikoId = null,
        public readonly array $metadata = [],
    ) {
    }

    public static function fromArray(array $item): self
    {
        return new self(
            iikoProductId: (string) $item['iiko_product_id'],
            quantity: (int) ($item['quantity'] ?? 1),
            price: (int) ($item['price'] ?? 0),
            comboIikoId: isset($item['combo_iiko_id']) ? (string) $item['combo_iiko_id'] : null,
            metadata: $item['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'iiko_product_id' => $this->iikoProductId,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'combo_iiko_id' => $this->comboIikoId,
            'metadata' => $this->metadata,
        ];
    }
}
