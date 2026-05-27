<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponIssueCondition;
use Modules\Shared\Enums\CouponKind;
use Modules\Shared\Enums\CouponStatus;

class DeliveryGuaranteeCouponIssueService
{
    public function issueFromDeliveredEvent(array $event): ?Coupon
    {
        $payload = $event['payload'] ?? $event;
        $eventType = $event['event_type'] ?? null;
        $status = $payload['status'] ?? null;

        if ($eventType !== 'Delivered' && $status !== 'Delivered') {
            return null;
        }

        $orderId = isset($payload['id']) ? (int) $payload['id'] : null;
        $eventId = isset($event['event_id']) ? (string) $event['event_id'] : null;
        $timestamps = $payload['timestamps'] ?? [];
        $cookingStartAt = $this->parseDate($timestamps['cooking_start_at'] ?? null);
        $deliveredAt = $this->parseDate($timestamps['delivered_at'] ?? null);

        if (! $orderId || ! $cookingStartAt || ! $deliveredAt) {
            return null;
        }

        $durationMinutes = (int) $cookingStartAt->diffInMinutes($deliveredAt, false);

        if ($durationMinutes <= 0) {
            return null;
        }

        $template = $this->findMatchingTemplate($durationMinutes);

        if (! $template) {
            return null;
        }

        return DB::transaction(function () use ($template, $payload, $orderId, $eventId, $durationMinutes, $cookingStartAt, $deliveredAt) {
            $existing = Coupon::query()
                ->where('issued_from_coupon_id', $template->id)
                ->where('source_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($eventId) {
                $existingByEvent = Coupon::query()
                    ->where('source_event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existingByEvent) {
                    return $existingByEvent;
                }
            }

            if (! $this->issueLimitsAllow($template, $payload)) {
                return null;
            }

            $customer = $payload['customer'] ?? [];
            $issuedCoupon = Coupon::query()->create([
                'code' => $this->generateCode($template, $orderId),
                'name' => $template->name.' #'.$orderId,
                'coupon_kind' => CouponKind::PublicCodeCoupon->value,
                'starts_at' => now(),
                'ends_at' => $template->ends_at,
                'status' => CouponStatus::Active->value,
                'usage_limit_total' => 1,
                'usage_limit_per_customer' => 1,
                'issue_limit_total' => null,
                'issue_limit_per_customer' => null,
                'issue_policy' => [
                    'source' => 'delivery_guarantee',
                    'template_coupon_id' => $template->id,
                    'delivery_duration_minutes' => $durationMinutes,
                    'cooking_start_at' => $cookingStartAt->toDateTimeString(),
                    'delivered_at' => $deliveredAt->toDateTimeString(),
                    'order_number' => $payload['number'] ?? null,
                    'source_key' => $payload['source_key'] ?? null,
                ],
                'auto_apply' => false,
                'visible_to_customer' => true,
                'requires_code_input' => true,
                'priority' => $template->priority,
                'stackable' => $template->stackable,
                'is_stackable_with_bellcoin' => $template->is_stackable_with_bellcoin,
                'is_stackable_with_other_coupons' => $template->is_stackable_with_other_coupons,
                'issued_from_coupon_id' => $template->id,
                'issued_customer_id' => isset($customer['id']) ? (int) $customer['id'] : null,
                'issued_customer_phone' => $customer['phone'] ?? null,
                'source_order_id' => $orderId,
                'source_event_id' => $eventId,
            ]);

            $this->copyUsageConditions($template, $issuedCoupon);
            $this->copyActions($template, $issuedCoupon);

            return $issuedCoupon;
        });
    }

    private function findMatchingTemplate(int $durationMinutes): ?Coupon
    {
        $templates = Coupon::query()
            ->with(['issueConditions', 'conditions', 'actions'])
            ->where('coupon_kind', CouponKind::IssuedCustomerCoupon->value)
            ->where('status', CouponStatus::Active->value)
            ->whereNull('issued_from_coupon_id')
            ->whereHas('issueConditions', fn ($query) => $query->where('condition_type', 'order.delivery_time_over'))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return $templates->first(fn (Coupon $coupon) => $this->issueConditionsMatch($coupon, $durationMinutes));
    }

    private function issueConditionsMatch(Coupon $coupon, int $durationMinutes): bool
    {
        foreach ($coupon->issueConditions as $condition) {
            if ($condition->condition_type !== 'order.delivery_time_over') {
                continue;
            }

            if (! $this->deliveryTimeConditionMatches($condition, $durationMinutes)) {
                return false;
            }
        }

        return true;
    }

    private function deliveryTimeConditionMatches(CouponIssueCondition $condition, int $durationMinutes): bool
    {
        $requiredMinutes = (int) ($condition->value ?? 35);
        $operator = $condition->operator ?: '>';

        return match ($operator) {
            '>=' => $durationMinutes >= $requiredMinutes,
            '=' => $durationMinutes === $requiredMinutes,
            default => $durationMinutes > $requiredMinutes,
        };
    }

    private function issueLimitsAllow(Coupon $template, array $payload): bool
    {
        if ($template->issue_limit_total !== null) {
            $issuedTotal = Coupon::query()
                ->where('issued_from_coupon_id', $template->id)
                ->count();

            if ($issuedTotal >= $template->issue_limit_total) {
                return false;
            }
        }

        if ($template->issue_limit_per_customer !== null) {
            $customer = $payload['customer'] ?? [];
            $customerId = isset($customer['id']) ? (int) $customer['id'] : null;
            $phone = $customer['phone'] ?? null;

            $issuedForCustomer = Coupon::query()
                ->where('issued_from_coupon_id', $template->id)
                ->when($customerId, fn ($query) => $query->where('issued_customer_id', $customerId))
                ->when(! $customerId && $phone, fn ($query) => $query->where('issued_customer_phone', $phone))
                ->when(! $customerId && ! $phone, fn ($query) => $query->whereRaw('1 = 0'))
                ->count();

            if ($issuedForCustomer >= $template->issue_limit_per_customer) {
                return false;
            }
        }

        return true;
    }

    private function copyUsageConditions(Coupon $template, Coupon $issuedCoupon): void
    {
        foreach ($template->conditions as $condition) {
            $issuedCoupon->conditions()->create($condition->only([
                'condition_type',
                'operator',
                'value',
                'payload',
            ]));
        }
    }

    private function copyActions(Coupon $template, Coupon $issuedCoupon): void
    {
        foreach ($template->actions as $action) {
            $issuedCoupon->actions()->create($action->only([
                'action_type',
                'value',
                'max_discount_amount',
                'product_iiko_id',
                'combo_iiko_id',
                'quantity',
                'price_override',
                'metadata',
            ]));
        }
    }

    private function generateCode(Coupon $template, int $orderId): string
    {
        $prefix = Str::of($template->code ?: 'GUARANT')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '')
            ->toString();

        return $prefix.'-'.$orderId.'-'.Str::upper(Str::random(6));
    }

    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
