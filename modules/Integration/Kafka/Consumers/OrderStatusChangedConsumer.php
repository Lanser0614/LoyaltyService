<?php

namespace Modules\Integration\Kafka\Consumers;

use Modules\Checkout\Services\CheckoutIncentiveService;
use Modules\Integration\Inbox\Services\EventInboxService;
use Modules\Promotion\Services\DeliveryGuaranteeCouponIssueService;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderStatusChangedConsumer
{
    public function __construct(
        private readonly EventInboxService $inbox,
        private readonly CheckoutIncentiveService $checkoutIncentives,
        private readonly DeliveryGuaranteeCouponIssueService $deliveryGuaranteeCoupons,
    ) {
    }

    public function handle(array $payload): void
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? 'order.status_changed');
        $orderPayload = $payload['payload'] ?? $payload;
        $event = $this->inbox->remember($eventId, $eventType, $payload);

        if (! $event) {
            return;
        }

        try {
            DB::transaction(function () use ($payload, $orderPayload, $eventType) {
                if (($orderPayload['status'] ?? null) === 'WaitCooking' && ! empty($orderPayload['incentive_application_id'])) {
                    $this->checkoutIncentives->commitByWaitCooking(
                        (string) $orderPayload['incentive_application_id'],
                        (int) ($orderPayload['order_id'] ?? $orderPayload['id']),
                    );
                }

                if ($eventType === 'Delivered' || ($orderPayload['status'] ?? null) === 'Delivered') {
                    $this->deliveryGuaranteeCoupons->issueFromDeliveredEvent($payload);
                }
            });

            $this->inbox->markProcessed($event);
        } catch (Throwable $exception) {
            $this->inbox->markFailed($event, $exception->getMessage());

            throw $exception;
        }
    }
}
