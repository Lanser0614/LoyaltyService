<?php

namespace Modules\Checkout\DTOs;

class CheckoutIncentiveRequest
{
    /**
     * @param array<int, CheckoutItemDTO> $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly int $orderTotal,
        public readonly array $items,
        public readonly ?string $couponCode = null,
        public readonly bool $useBellCoin = false,
        public readonly int $bellCoinAmount = 0,
        public readonly int $deliveryFee = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: (int) $data['customer_id'],
            orderTotal: (int) $data['order_total'],
            items: array_map(fn (array $item) => CheckoutItemDTO::fromArray($item), $data['items'] ?? []),
            couponCode: isset($data['coupon_code']) ? (string) $data['coupon_code'] : null,
            useBellCoin: (bool) ($data['use_bellcoin'] ?? false),
            bellCoinAmount: (int) ($data['bellcoin_amount'] ?? 0),
            deliveryFee: (int) ($data['delivery_fee'] ?? 0),
        );
    }

    public function usesCoupon(): bool
    {
        return $this->couponCode !== null;
    }
}
