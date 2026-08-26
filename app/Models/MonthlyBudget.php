<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyBudget extends Model
{
    protected $fillable = ['user_id', 'year', 'month'];

    protected $casts = ['year' => 'integer', 'month' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(MonthlyBudgetLine::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    /** Categorie create solo per questo mese. */
    public function categories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class)->orderBy('order');
    }

    /** Sottocategorie create solo per questo mese. */
    public function subcategories(): HasMany
    {
        return $this->hasMany(BudgetSubcategory::class)->orderBy('order');
    }
}
