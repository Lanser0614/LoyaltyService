<?php

namespace Modules\Checkout\Services;

use Modules\Checkout\DTOs\CheckoutIncentiveRequest;
use Modules\Checkout\DTOs\IncentivePreviewResult;
use Modules\Checkout\Models\OrderIncentiveApplication;
use Modules\IncentivePolicy\Services\StackingPolicyService;
use Modules\Loyalty\Services\BellCoinLifecycleService;
use Modules\Loyalty\Services\BellCoinReserveService;
use Modules\Promotion\DTOs\ActionContext;
use Modules\Promotion\DTOs\ConditionContext;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Services\ConditionEvaluator;
use Modules\Promotion\Services\CouponActionResolverService;
use Modules\Promotion\Services\CouponResolverService;
use Modules\Promotion\Services\CouponUsageLimitService;
use Modules\Shared\Enums\CouponUsageStatus;
use Modules\Shared\Enums\IncentiveApplicationStatus;
use Modules\Shared\Exceptions\IncentiveRejectedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutIncentiveService
{
    public function __construct(
        private readonly CouponResolverService $couponResolver,
        private readonly CouponUsageLimitService $usageLimits,
        private readonly ConditionEvaluator $conditions,
        private readonly CouponActionResolverService $actions,
        private readonly BellCoinReserveService $bellCoinReserve,
        private readonly BellCoinLifecycleService $bellCoinLifecycle,
        private readonly StackingPolicyService $stackingPolicy,
    ) {
    }

    public function preview(CheckoutIncentiveRequest $request): IncentivePreviewResult
    {
        try {
            $calculation = $this->calculate($request);

            return new IncentivePreviewResult(
                allowed: true,
                discountAmount: $calculation['discount_amount'],
                freeItems: $calculation['free_items'],
                finalAmount: $calculation['final_amount'],
                paymentAmount: $calculation['payment_amount'],
                snapshot: $calculation['snapshot'],
            );
        } catch (IncentiveRejectedException $exception) {
            return IncentivePreviewResult::rejected($exception->reason);
        }
    }

    public function apply(CheckoutIncentiveRequest $request): array
    {
        $calculation = $this->calculate($request);

        return DB::transaction(function () use ($request, $calculation) {
            $couponUsage = $calculation['coupon']
                ? $this->reserveCouponUsage($calculation['coupon'], $request, $calculation)
                : null;

            $bellCoinReservation = $this->usesBellCoin($request)
                ? $this->bellCoinReserve->reserve($request->customerId, $request->bellCoinAmount, [
                    'source' => 'checkout_apply',
                ])
                : null;

            $application = OrderIncentiveApplication::query()->create([
                'incentive_application_id' => (string) Str::uuid(),
                'customer_id' => $request->customerId,
                'coupon_usage_id' => $couponUsage?->id,
                'bellcoin_transaction_id' => $bellCoinReservation?->id,
                'total_discount_amount' => $calculation['discount_amount'],
                'incentives_snapshot' => $calculation['snapshot'],
                'status' => IncentiveApplicationStatus::Reserved,
                'reserved_at' => now(),
            ]);

            return [
                'incentive_application_id' => $application->incentive_application_id,
                'final_items' => [
                    ...array_map(fn ($item) => $item->toArray(), $request->items),
                    ...array_map(fn ($item) => $item->toArray(), $calculation['free_items']),
                ],
                'final_amount' => $calculation['final_amount'],
                'payment_amount' => $calculation['payment_amount'],
                'discount_amount' => $calculation['discount_amount'],
            ];
        });
    }

    public function cancel(string $incentiveApplicationId): void
    {
        DB::transaction(function () use ($incentiveApplicationId) {
            $application = OrderIncentiveApplication::query()
                ->where('incentive_application_id', $incentiveApplicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== IncentiveApplicationStatus::Reserved) {
                return;
            }

            if ($application->coupon_usage_id) {
                $usage = CouponUsage::query()->lockForUpdate()->find($application->coupon_usage_id);
                $usage?->update([
                    'status' => CouponUsageStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
            }

            if ($application->bellcoin_transaction_id) {
                $this->bellCoinLifecycle->releaseReservation($application->bellcoin_transaction_id, 'CHECKOUT_CANCEL');
            }

            $application->update([
                'status' => IncentiveApplicationStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }

    public function commitByWaitCooking(string $incentiveApplicationId, int $orderId): void
    {
        DB::transaction(function () use ($incentiveApplicationId, $orderId) {
            $application = OrderIncentiveApplication::query()
                ->where('incentive_application_id', $incentiveApplicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== IncentiveApplicationStatus::Reserved) {
                return;
            }

            if ($application->coupon_usage_id) {
                $usage = CouponUsage::query()->lockForUpdate()->find($application->coupon_usage_id);
                $usage?->update([
                    'order_id' => $orderId,
                    'status' => CouponUsageStatus::Applied,
                    'applied_at' => now(),
                ]);
            }

            if ($application->bellcoin_transaction_id) {
                $this->bellCoinLifecycle->commitReservation($application->bellcoin_transaction_id, $orderId);
            }

            $application->update([
                'order_id' => $orderId,
                'status' => IncentiveApplicationStatus::Applied,
                'applied_at' => now(),
            ]);
        });
    }

    private function calculate(CheckoutIncentiveRequest $request): array
    {
        $coupon = $this->couponResolver->resolve($request);
        $actionResult = null;

        if ($coupon) {
            $coupon->loadMissing(['conditions', 'actions']);
            $this->couponResolver->assertCouponActive($coupon);
            $this->usageLimits->assertCanUse($coupon, $request->customerId);
            $this->conditions->assertMatches($coupon, new ConditionContext($request));
            $actionResult = $this->actions->resolve($coupon, new ActionContext($request));
        }

        if ($this->usesBellCoin($request)) {
            $this->bellCoinReserve->validateRedemption(
                $request->customerId,
                $request->bellCoinAmount,
                $request->orderTotal,
                $request->deliveryFee,
            );
        }

        $couponDiscount = $actionResult?->discountAmount ?? 0;
        $bellCoinDiscount = $this->usesBellCoin($request) ? $request->bellCoinAmount : 0;
        $totalDiscount = $couponDiscount + $bellCoinDiscount;
        $actionTypes = $actionResult?->incentiveTypes ?? [];

        $this->stackingPolicy->assertAllowed($coupon, $this->usesBellCoin($request), $actionTypes, $totalDiscount);

        $finalAmount = max(0, $request->orderTotal - $totalDiscount);

        return [
            'coupon' => $coupon,
            'coupon_discount_amount' => $couponDiscount,
            'discount_amount' => $totalDiscount,
            'free_items' => $actionResult?->freeItems ?? [],
            'final_amount' => $finalAmount,
            'payment_amount' => $finalAmount,
            'snapshot' => [
                'coupon' => $coupon ? [
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'discount_amount' => $couponDiscount,
                    'actions' => $actionResult?->snapshot ?? [],
                ] : null,
                'bellcoin' => $this->usesBellCoin($request) ? [
                    'amount' => $request->bellCoinAmount,
                ] : null,
                'free_items' => array_map(fn ($item) => $item->toArray(), $actionResult?->freeItems ?? []),
                'stacking_policy' => [
                    'coupon_with_bellcoin' => $coupon?->is_stackable_with_bellcoin ?? false,
                ],
            ],
        ];
    }

    private function reserveCouponUsage(Coupon $coupon, CheckoutIncentiveRequest $request, array $calculation): CouponUsage
    {
        return CouponUsage::query()->create([
            'coupon_id' => $coupon->id,
            'customer_id' => $request->customerId,
            'status' => CouponUsageStatus::Reserved,
            'discount_amount' => $calculation['coupon_discount_amount'],
            'free_items_snapshot' => array_map(fn ($item) => $item->toArray(), $calculation['free_items']),
            'reservation_key' => (string) Str::uuid(),
            'validation_snapshot_id' => null,
        ]);
    }

    private function usesBellCoin(CheckoutIncentiveRequest $request): bool
    {
        return $request->useBellCoin && $request->bellCoinAmount > 0;
    }
}
