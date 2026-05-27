<?php

use Modules\Shared\Enums\IncentiveApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_incentive_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('incentive_application_id')->unique();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreignId('coupon_usage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bellcoin_transaction_id')->nullable()->constrained('loyalty_ledger_transactions')->nullOnDelete();
            $table->integer('total_discount_amount')->default(0);
            $table->json('incentives_snapshot')->nullable();
            $table->string('status')->default(IncentiveApplicationStatus::Reserved->value)->index();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_incentive_applications');
    }
};
