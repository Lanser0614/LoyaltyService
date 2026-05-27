<?php

namespace Modules\Shared\Enums;

enum FailureReason: string
{
    case CouponNotFound = 'COUPON_NOT_FOUND';
    case CustomerCouponNotFound = 'CUSTOMER_COUPON_NOT_FOUND';
    case CustomerCouponNotOwnedByCustomer = 'CUSTOMER_COUPON_NOT_OWNED_BY_CUSTOMER';
    case CustomerCouponNotAvailable = 'CUSTOMER_COUPON_NOT_AVAILABLE';
    case CouponNotActive = 'COUPON_NOT_ACTIVE';
    case CouponExpired = 'COUPON_EXPIRED';
    case CouponUsageLimitReached = 'COUPON_USAGE_LIMIT_REACHED';
    case CouponCustomerLimitReached = 'COUPON_CUSTOMER_LIMIT_REACHED';
    case CouponConditionNotMatched = 'COUPON_CONDITION_NOT_MATCHED';
    case CouponActionNotFound = 'COUPON_ACTION_NOT_FOUND';
    case CouponActionProductInactive = 'COUPON_ACTION_PRODUCT_INACTIVE';
    case BellCoinAccountNotFound = 'BELLCOIN_ACCOUNT_NOT_FOUND';
    case BellCoinInsufficientBalance = 'BELLCOIN_INSUFFICIENT_BALANCE';
    case BellCoinRedemptionLimitExceeded = 'BELLCOIN_REDEMPTION_LIMIT_EXCEEDED';
    case BellCoinReservationNotFound = 'BELLCOIN_RESERVATION_NOT_FOUND';
    case CouponAndBellCoinNotStackable = 'COUPON_AND_BELLCOIN_NOT_STACKABLE';
    case FreeDeliveryAndBellCoinNotStackable = 'FREE_DELIVERY_AND_BELLCOIN_NOT_STACKABLE';
    case FreeProductAndBellCoinNotStackable = 'FREE_PRODUCT_AND_BELLCOIN_NOT_STACKABLE';
    case FreeComboAndBellCoinNotStackable = 'FREE_COMBO_AND_BELLCOIN_NOT_STACKABLE';
    case MultipleCouponsNotAllowed = 'MULTIPLE_COUPONS_NOT_ALLOWED';
    case MaxTotalDiscountExceeded = 'MAX_TOTAL_DISCOUNT_EXCEEDED';
    case InvalidCartSnapshot = 'INVALID_CART_SNAPSHOT';
    case InvalidComboStructure = 'INVALID_COMBO_STRUCTURE';
    case InvalidConditionDefinition = 'INVALID_CONDITION_DEFINITION';
    case InvalidActionDefinition = 'INVALID_ACTION_DEFINITION';
    case EventAlreadyProcessed = 'EVENT_ALREADY_PROCESSED';
    case EventOutOfOrder = 'EVENT_OUT_OF_ORDER';
    case InternalError = 'INTERNAL_ERROR';
}
