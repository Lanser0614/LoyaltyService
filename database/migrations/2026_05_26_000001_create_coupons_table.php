<?php

use Modules\Shared\Enums\CouponKind;
use Modules\Shared\Enums\CouponStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('coupon_kind')->default(CouponKind::PublicCodeCoupon->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default(CouponStatus::Draft->value)->index();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('issue_limit_total')->nullable();
            $table->unsignedInteger('issue_limit_per_customer')->nullable();
            $table->json('issue_policy')->nullable();
            $table->boolean('auto_apply')->default(false);
            $table->boolean('visible_to_customer')->default(true);
            $table->boolean('requires_code_input')->default(true);
            $table->integer('priority')->default(0);
            $table->boolean('stackable')->default(false);
            $table->boolean('is_stackable_with_bellcoin')->default(false);
            $table->boolean('is_stackable_with_other_coupons')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
