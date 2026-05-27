<?php

use Modules\Shared\Enums\CouponUsageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('status')->default(CouponUsageStatus::Reserved->value)->index();
            $table->integer('discount_amount')->default(0);
            $table->json('free_items_snapshot')->nullable();
            $table->uuid('reservation_key')->unique();
            $table->uuid('validation_snapshot_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
