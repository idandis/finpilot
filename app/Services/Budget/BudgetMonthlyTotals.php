<?php

namespace App\Services\Budget;

use App\Models\BudgetCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Totali reali registrati nel budget mensile, mese per mese: quanto è
 * entrato e quanto è uscito davvero, ricavato dai movimenti.
 */
class BudgetMonthlyTotals
{
    /**
     * @return array<int, array{year: int, month: int, income: float, expense: float}>
     */
    public function perMonth(User $user): array
    {
        $rows = DB::table('budget_expenses')
            ->join('monthly_budgets', 'monthly_budgets.id', '=', 'budget_expenses.monthly_budget_id')
            ->join('budget_subcategories', 'budget_subcategories.id', '=', 'budget_expenses.budget_subcategory_id')
            ->join('budget_categories', 'budget_categories.id', '=', 'budget_subcategories.budget_category_id')
            ->where('monthly_budgets.user_id', $user->id)
            ->groupBy('monthly_budgets.year', 'monthly_budgets.month', 'budget_categories.type')
            ->selectRaw('monthly_budgets.year as year, monthly_budgets.month as month, budget_categories.type as type, SUM(budget_expenses.amount) as total')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $key = "{$row->year}-{$row->month}";

            $totals[$key] ??= [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
                'income' => 0.0,
                'expense' => 0.0,
            ];

            $bucket = $row->type === BudgetCategory::TYPE_INCOME ? 'income' : 'expense';
            $totals[$key][$bucket] += (float) $row->total;
        }

        return array_values($totals);
    }
}
