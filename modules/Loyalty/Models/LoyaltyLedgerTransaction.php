<?php

namespace Modules\Loyalty\Models;

use Modules\Shared\Enums\LedgerTransactionStatus;
use Modules\Shared\Enums\LedgerTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyLedgerTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'account_id',
        'order_id',
        'event_id',
        'type',
        'amount',
        'balance_after',
        'status',
        'reason',
        'related_transaction_id',
        'metadata',
    ];

    protected $casts = [
        'type' => LedgerTransactionType::class,
        'status' => LedgerTransactionStatus::class,
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'account_id');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }
}
