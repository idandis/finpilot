<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetSubcategory extends Model
{
    protected $fillable = ['monthly_budget_id', 'name', 'order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    /**
     * Il mese a cui la sottocategoria è limitata, null se fa parte della
     * configurazione comune a tutti i mesi.
     */
    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(MonthlyBudgetLine::class);
    }

    /** Sottocategorie della configurazione comune. */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('monthly_budget_id');
    }

    /** Sottocategorie create solo per un dato mese. */
    public function scopeForMonthlyBudget(Builder $query, ?int $monthlyBudgetId): Builder
    {
        return $query->where('monthly_budget_id', $monthlyBudgetId);
    }
}
