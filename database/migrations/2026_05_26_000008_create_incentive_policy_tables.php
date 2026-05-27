<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_stacking_rules', function (Blueprint $table) {
            $table->id();
            $table->string('primary_incentive_type')->index();
            $table->string('secondary_incentive_type')->index();
            $table->boolean('is_allowed')->default(false);
            $table->integer('priority')->default(0);
            $table->string('failure_reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('incentive_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type')->unique();
            $table->integer('value');
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_limit_rules');
        Schema::dropIfExists('incentive_stacking_rules');
    }
};
