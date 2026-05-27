<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('coupons')->whereNull('code')->update(['code' => DB::raw("'coupon-' || id")]);
        DB::statement('ALTER TABLE coupons ALTER COLUMN code SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE coupons ALTER COLUMN code DROP NOT NULL');
    }
};
