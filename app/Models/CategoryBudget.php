<?php

namespace App\Models;

use Database\Factories\CategoryBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $transaction_category_id
 * @property int|null $card_id
 * @property string $monthly_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'transaction_category_id', 'card_id', 'monthly_amount'])]
class CategoryBudget extends Model
{
    /** @use HasFactory<CategoryBudgetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
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
     * @return BelongsTo<TransactionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
