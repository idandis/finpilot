<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetExpense extends Model
{
    protected $fillable = ['monthly_budget_id', 'budget_subcategory_id', 'amount', 'recorded_at', 'description'];
    protected $casts = ['amount' => 'decimal:2', 'recorded_at' => 'datetime'];

    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }
}
