<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type')->index();
            $table->string('operator')->nullable();
            $table->string('value')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_issue_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type')->index();
            $table->string('operator')->nullable();
            $table->string('value')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_issue_conditions');
        Schema::dropIfExists('coupon_conditions');
    }
};
