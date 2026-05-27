<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('action_type')->index();
            $table->integer('value')->nullable();
            $table->integer('max_discount_amount')->nullable();
            $table->string('product_iiko_id')->nullable();
            $table->string('combo_iiko_id')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->integer('price_override')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_actions');
    }
};
