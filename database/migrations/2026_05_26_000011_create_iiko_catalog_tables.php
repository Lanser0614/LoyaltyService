<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('country_code')->nullable();
            $table->string('currency')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('iiko_cities', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('external_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
        });

        Schema::create('iiko_product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('external_id')->index();
            $table->string('parent_external_id')->nullable()->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
        });

        Schema::create('iiko_products', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('external_id')->index();
            $table->string('group_external_id')->nullable()->index();
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
        });

        Schema::create('iiko_menus', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('iiko_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iiko_menu_id')->constrained('iiko_menus')->cascadeOnDelete();
            $table->string('organization_id')->index();
            $table->string('product_external_id')->index();
            $table->integer('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('iiko_product_availability', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('terminal_group_id')->nullable()->index();
            $table->string('product_external_id')->index();
            $table->boolean('is_available')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('iiko_combos', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('external_id')->index();
            $table->string('name');
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
        });

        Schema::create('iiko_combo_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iiko_combo_id')->constrained('iiko_combos')->cascadeOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('iiko_combo_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iiko_combo_group_id')->constrained('iiko_combo_groups')->cascadeOnDelete();
            $table->string('product_external_id')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_combo_group_items');
        Schema::dropIfExists('iiko_combo_groups');
        Schema::dropIfExists('iiko_combos');
        Schema::dropIfExists('iiko_product_availability');
        Schema::dropIfExists('iiko_menu_items');
        Schema::dropIfExists('iiko_menus');
        Schema::dropIfExists('iiko_products');
        Schema::dropIfExists('iiko_product_groups');
        Schema::dropIfExists('iiko_cities');
        Schema::dropIfExists('iiko_organizations');
    }
};
