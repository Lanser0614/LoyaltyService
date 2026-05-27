<?php

use Modules\Shared\Enums\LedgerTransactionStatus;
use Modules\Shared\Enums\LedgerTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreignId('account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('event_id')->nullable()->index();
            $table->string('type')->default(LedgerTransactionType::Accrual->value)->index();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('status')->default(LedgerTransactionStatus::Completed->value)->index();
            $table->string('reason')->nullable();
            $table->foreignId('related_transaction_id')->nullable()->constrained('loyalty_ledger_transactions')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger_transactions');
    }
};
