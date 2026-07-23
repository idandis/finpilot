<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $financial_account_id
 * @property int|null $card_id
 * @property int|null $transaction_category_id
 * @property Carbon $transaction_date
 * @property string $description
 * @property string|null $isin
 * @property string|null $quantity
 * @property string $amount
 * @property string $direction
 * @property string $dedup_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['financial_account_id', 'transaction_date', 'description', 'isin', 'quantity', 'amount', 'direction', 'card_id', 'transaction_category_id', 'dedup_hash'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const DIRECTIONS = ['income', 'expense'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'quantity' => 'decimal:8',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<TransactionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
}
