<?php

namespace App\Services\Budget;

use App\Models\BudgetCategory;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Compone la struttura di categorie visibile in un dato mese: la configurazione
 * comune (monthly_budget_id null) più le voci temporanee di quel mese.
 */
class BudgetStructureResolver
{
    public function findMonthlyBudget(User $user, int $year, int $month): ?MonthlyBudget
    {
        return $user->monthlyBudgets()
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function firstOrCreateMonthlyBudget(User $user, int $year, int $month): MonthlyBudget
    {
        return MonthlyBudget::firstOrCreate([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * @return Collection<int, BudgetCategory>
     */
    public function categoriesFor(User $user, ?MonthlyBudget $monthlyBudget): Collection
    {
        $monthlyBudgetId = $monthlyBudget?->id;

        return BudgetCategory::query()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereNull('monthly_budget_id')
                ->orWhere('monthly_budget_id', $monthlyBudgetId))
            ->with(['subcategories' => fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->whereNull('monthly_budget_id')
                    ->orWhere('monthly_budget_id', $monthlyBudgetId))])
            ->orderByRaw('monthly_budget_id is null desc')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Solo la configurazione comune a tutti i mesi.
     *
     * @return Collection<int, BudgetCategory>
     */
    public function globalCategories(User $user): Collection
    {
        return BudgetCategory::query()
            ->where('user_id', $user->id)
            ->global()
            ->with(['subcategories' => fn ($query) => $query->global()])
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }
}
