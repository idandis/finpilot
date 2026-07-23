<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $financial_account_id
 * @property string $name
 * @property string $type
 * @property string|null $last_four_digits
 * @property string|null $circuit
 * @property string|null $color
 * @property string|null $icon
 * @property string|null $owner_name
 * @property string|null $iban
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'last_four_digits', 'circuit', 'is_active', 'color', 'icon', 'owner_name', 'iban', 'financial_account_id'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    public const TYPES = ['debit', 'credit', 'prepaid'];

    public const ICONS = ['credit-card', 'wallet', 'landmark', 'piggy-bank', 'banknote', 'coins'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
