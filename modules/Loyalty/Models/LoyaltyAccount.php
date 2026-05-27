<?php

namespace Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'balance',
        'reserved_balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'integer',
        'reserved_balance' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerTransaction::class, 'account_id');
    }

    public function availableBalance(): int
    {
        return max(0, $this->balance - $this->reserved_balance);
    }
}
