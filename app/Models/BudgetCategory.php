<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    public const TYPE_EXPENSE = 'expense';

    public const TYPE_INCOME = 'income';

    public const TYPES = [self::TYPE_EXPENSE, self::TYPE_INCOME];

    protected $fillable = ['monthly_budget_id', 'name', 'color', 'type', 'order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Il mese a cui la categoria è limitata, null se fa parte della
     * configurazione comune a tutti i mesi.
     */
    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    /** L'ordine manuale vince; a parità, le voci restano in ordine di aggiunta. */
    public function subcategories(): HasMany
    {
        return $this->hasMany(BudgetSubcategory::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    /** Categorie di entrata oppure di uscita. */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    /** Categorie della configurazione comune. */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('monthly_budget_id');
    }

    /** Categorie create solo per un dato mese. */
    public function scopeForMonthlyBudget(Builder $query, ?int $monthlyBudgetId): Builder
    {
        return $query->where('monthly_budget_id', $monthlyBudgetId);
    }
}
