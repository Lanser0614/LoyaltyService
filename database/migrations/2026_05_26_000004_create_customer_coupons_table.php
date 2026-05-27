<?php

use Modules\Shared\Enums\CustomerCouponStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('code')->nullable()->index();
            $table->string('campaign_key')->nullable();
            $table->string('status')->default(CustomerCouponStatus::Available->value)->index();
            $table->string('issued_reason')->nullable();
            $table->string('issued_by_type')->nullable();
            $table->unsignedBigInteger('issued_by_id')->nullable();
            $table->string('source_event_id')->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'coupon_id', 'campaign_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_coupons');
    }
};
