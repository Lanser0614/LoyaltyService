<?php

namespace Modules\Shared\Enums;

enum IncentiveType: string
{
    case Coupon = 'coupon';
    case BellCoin = 'bellcoin';
    case FreeDelivery = 'free_delivery';
    case FreeProduct = 'free_product';
    case FreeCombo = 'free_combo';
    case ManualDiscount = 'manual_discount';
    case PromotionDiscount = 'promotion_discount';
}
