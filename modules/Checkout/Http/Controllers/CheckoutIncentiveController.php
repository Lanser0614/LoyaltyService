<?php

namespace Modules\Checkout\Http\Controllers;

use Modules\Checkout\DTOs\CheckoutIncentiveRequest;
use Modules\Checkout\Services\CheckoutIncentiveService;
use App\Http\Controllers\Controller;
use Modules\Shared\Exceptions\IncentiveRejectedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutIncentiveController extends Controller
{
    public function __construct(private readonly CheckoutIncentiveService $service)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        $payload = $this->validatedCheckoutPayload($request);

        return response()->json(
            $this->service->preview(CheckoutIncentiveRequest::fromArray($payload))->toArray()
        );
    }

    public function apply(Request $request): JsonResponse
    {
        $payload = $this->validatedCheckoutPayload($request);

        try {
            return response()->json(
                $this->service->apply(CheckoutIncentiveRequest::fromArray($payload))
            );
        } catch (IncentiveRejectedException $exception) {
            return response()->json([
                'allowed' => false,
                'failure_reason' => $exception->reason->value,
            ], 422);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'incentive_application_id' => ['required', 'string'],
        ]);

        $this->service->cancel($payload['incentive_application_id']);

        return response()->json(['cancelled' => true]);
    }

    private function validatedCheckoutPayload(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer'],
            'order_total' => ['required', 'integer', 'min:0'],
            'coupon_code' => ['nullable', 'string'],
            'use_bellcoin' => ['sometimes', 'boolean'],
            'bellcoin_amount' => ['sometimes', 'integer', 'min:0'],
            'delivery_fee' => ['sometimes', 'integer', 'min:0'],
            'items' => ['sometimes', 'array'],
            'items.*.iiko_product_id' => ['required_with:items', 'string'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.price' => ['sometimes', 'integer', 'min:0'],
            'items.*.combo_iiko_id' => ['nullable', 'string'],
            'items.*.metadata' => ['sometimes', 'array'],
        ]);
    }
}
