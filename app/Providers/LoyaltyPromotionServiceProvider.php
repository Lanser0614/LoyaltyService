<?php

namespace App\Providers;

use Modules\CustomerInsights\Contracts\CustomerSegmentProviderInterface;
use Modules\CustomerInsights\Services\NullCustomerSegmentProvider;
use Modules\Promotion\Handlers\Actions\FixedDiscountHandler;
use Modules\Promotion\Handlers\Actions\FreeComboHandler;
use Modules\Promotion\Handlers\Actions\FreeDeliveryHandler;
use Modules\Promotion\Handlers\Actions\FreeProductHandler;
use Modules\Promotion\Handlers\Actions\PercentDiscountHandler;
use Modules\Promotion\Handlers\Conditions\CartHasComboConditionHandler;
use Modules\Promotion\Handlers\Conditions\CustomerSegmentConditionHandler;
use Modules\Promotion\Handlers\Conditions\MinAmountConditionHandler;
use Modules\Promotion\Registries\ActionHandlerRegistry;
use Modules\Promotion\Registries\ConditionHandlerRegistry;
use Illuminate\Support\ServiceProvider;

class LoyaltyPromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerSegmentProviderInterface::class, NullCustomerSegmentProvider::class);

        $this->app->singleton(ConditionHandlerRegistry::class, function ($app) {
            $registry = new ConditionHandlerRegistry();
            $registry->register('cart.min_amount', $app->make(MinAmountConditionHandler::class));
            $registry->register('customer.segment', $app->make(CustomerSegmentConditionHandler::class));
            $registry->register('cart.has_combo', $app->make(CartHasComboConditionHandler::class));

            return $registry;
        });

        $this->app->singleton(ActionHandlerRegistry::class, function ($app) {
            $registry = new ActionHandlerRegistry();
            $registry->register('percent', $app->make(PercentDiscountHandler::class));
            $registry->register('fixed', $app->make(FixedDiscountHandler::class));
            $registry->register('free_delivery', $app->make(FreeDeliveryHandler::class));
            $registry->register('free_product', $app->make(FreeProductHandler::class));
            $registry->register('free_combo', $app->make(FreeComboHandler::class));

            return $registry;
        });
    }
}
