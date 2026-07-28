<?php

namespace App\Services\Finance;

use App\Models\Card;
use App\Models\CategoryBudget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SpendingSummaryCalculator
{
    /**
     * Actual spend per category for a given month, compared against the
     * user's monthly budget for that category (set on /budgets) - the
     * "actual vs target" comparison BudgetController's own docblock
     * explicitly leaves for later. Only categories with either spend or a
     * budget set are returned, sorted by spend descending, so "where am I
     * overspending" is just the top of the list.
     *
     * @param  Collection<int, Card>  $cards  the user's cards, scoped the same way AccountBalanceCalculator does
     * @param  string|null  $month  'Y-m', defaults to the current month
     * @return array<int, array{category_id: int, name: string, spent: float, budget: float|null, remaining: float|null, percent_used: float|null}>
     */
    public function calculate(User $user, Collection $cards, ?string $month = null): array
    {
        $reference = $month !== null ? Carbon::createFromFormat('Y-m', $month) : Carbon::now();
        $start = $reference->copy()->startOfMonth();
        $end = $reference->copy()->endOfMonth();

        $spentByCategory = Transaction::query()
            ->whereIn('card_id', $cards->pluck('id'))
            ->where('direction', 'expense')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_category_id, SUM(amount) as total')
            ->groupBy('transaction_category_id')
            ->pluck('total', 'transaction_category_id')
            ->map(fn ($total) => (float) $total);

        $budgets = CategoryBudget::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('transaction_category_id');

        $categories = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->get();

        return $categories
            ->map(function (TransactionCategory $category) use ($spentByCategory, $budgets) {
                $spent = round($spentByCategory->get($category->id, 0.0), 2);
                $budget = $budgets->get($category->id);
                $budgetAmount = $budget ? (float) $budget->monthly_amount : null;

                return [
                    'category_id' => $category->id,
                    'name' => $category->name,
                    'spent' => $spent,
                    'budget' => $budgetAmount,
                    'remaining' => $budgetAmount !== null ? round($budgetAmount - $spent, 2) : null,
                    'percent_used' => ($budgetAmount !== null && $budgetAmount > 0)
                        ? round(($spent / $budgetAmount) * 100, 1)
                        : null,
                ];
            })
            ->filter(fn (array $row) => $row['spent'] > 0 || $row['budget'] !== null)
            ->sortByDesc('spent')
            ->values()
            ->all();
    }
}
