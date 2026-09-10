<?php

namespace App\Models;

use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string|null $bank_name
 * @property string|null $iban
 * @property string|null $holder_name
 * @property string $currency
 * @property string $initial_balance
 * @property string|null $color
 * @property string|null $icon
 * @property int $position Posizione decisa a mano, trascinando le righe.
 * @property bool $is_active
 * @property bool $hidden_from_stats Archiviato: fuori dai totali e dalle scelte.
 * @property bool $excluded_from_stats Fuori dalle statistiche, movimenti compresi.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'bank_name', 'iban', 'holder_name', 'currency', 'initial_balance', 'color', 'icon', 'position', 'is_active', 'hidden_from_stats', 'excluded_from_stats'])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory;

    public const TYPES = ['checking', 'debit_card', 'credit_card', 'prepaid_card', 'cash'];

    public const ICONS = ['credit-card', 'wallet', 'landmark', 'piggy-bank', 'banknote', 'coins'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'hidden_from_stats' => 'boolean',
            'excluded_from_stats' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<BudgetExpense, $this>
     */
    public function budgetExpenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }
}
