<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $monthly_budget_id
 * @property int $budget_subcategory_id
 * @property int|null $financial_account_id
 * @property int|null $recorded_by_user_id
 * @property string $amount
 * @property string|null $description
 * @property Carbon|null $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BudgetExpense extends Model
{
    protected $fillable = ['monthly_budget_id', 'budget_subcategory_id', 'financial_account_id', 'recorded_by_user_id', 'amount', 'recorded_at', 'description'];

    protected $casts = ['amount' => 'decimal:2', 'recorded_at' => 'datetime'];

    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }

    /** Il conto o la carta da cui il movimento è passato: nullo se in contanti. */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * Chi l'ha scritto. Conta in un budget condiviso; sui movimenti registrati
     * prima che si tenesse il conto è nullo.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
