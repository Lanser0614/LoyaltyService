<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('issued_from_coupon_id')->nullable()->after('is_stackable_with_other_coupons')->constrained('coupons')->nullOnDelete();
            $table->unsignedBigInteger('issued_customer_id')->nullable()->after('issued_from_coupon_id')->index();
            $table->string('issued_customer_phone')->nullable()->after('issued_customer_id')->index();
            $table->unsignedBigInteger('source_order_id')->nullable()->after('issued_customer_phone')->index();
            $table->string('source_event_id')->nullable()->after('source_order_id')->unique();

            $table->unique(['issued_from_coupon_id', 'source_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique(['issued_from_coupon_id', 'source_order_id']);
            $table->dropUnique(['source_event_id']);
            $table->dropConstrainedForeignId('issued_from_coupon_id');
            $table->dropColumn([
                'issued_customer_id',
                'issued_customer_phone',
                'source_order_id',
                'source_event_id',
            ]);
        });
    }
};
