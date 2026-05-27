<?php

use Modules\Checkout\Http\Controllers\CheckoutIncentiveController;
use Modules\CustomerWallet\Http\Controllers\CustomerCouponController;
use Modules\Loyalty\Http\Controllers\LoyaltyBalanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/incentives/checkout/preview', [CheckoutIncentiveController::class, 'preview']);
Route::post('/incentives/checkout/apply', [CheckoutIncentiveController::class, 'apply']);
Route::post('/incentives/checkout/cancel', [CheckoutIncentiveController::class, 'cancel']);

Route::get('/customers/{customerId}/coupons/available', [CustomerCouponController::class, 'available']);
Route::get('/customers/{customerId}/loyalty/balance', [LoyaltyBalanceController::class, 'show']);
