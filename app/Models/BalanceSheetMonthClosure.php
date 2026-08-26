<?php

namespace App\Models;

use Database\Factories\BalanceSheetMonthClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $year
 * @property int|null $month
 * @property string $income_total
 * @property string $expense_total
 * @property string $cash_flow
 * @property string $cash_balance_after
 * @property array<string, mixed>|null $effects
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'month', 'income_total', 'expense_total', 'cash_flow', 'cash_balance_after', 'effects'])]
class BalanceSheetMonthClosure extends Model
{
    /** @use HasFactory<BalanceSheetMonthClosureFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'income_total' => 'decimal:2',
            'expense_total' => 'decimal:2',
            'cash_flow' => 'decimal:2',
            'cash_balance_after' => 'decimal:2',
            'effects' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
