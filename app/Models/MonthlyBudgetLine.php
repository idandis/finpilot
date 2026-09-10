<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyBudgetLine extends Model
{
    protected $fillable = ['monthly_budget_id', 'budget_subcategory_id', 'planned_amount'];

    protected $casts = ['planned_amount' => 'decimal:2'];

    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }
}
