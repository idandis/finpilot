<?php

namespace App\Services\Budget;

use App\Models\BudgetCategory;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quanto è entrato e uscito da ogni conto: i movimenti del budget più i
 * trasferimenti tra conti.
 *
 * Il verso di un movimento non sta sul movimento ma sulla categoria a cui
 * appartiene, perciò il conteggio passa dalle sottocategorie: due join e una
 * somma per conto, invece di leggersi in memoria tutti i movimenti di sempre.
 */
class BudgetAccountBalances
{
    private const NO_MOVEMENTS = ['income' => 0.0, 'expense' => 0.0, 'count' => 0];

    /**
     * @param  Collection<int, FinancialAccount>  $accounts
     * @return array<int, array{income: float, expense: float, count: int}>
     */
    public function movementsOf(Collection $accounts): array
    {
        $ids = $accounts->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('budget_expenses')
            ->join('budget_subcategories', 'budget_subcategories.id', '=', 'budget_expenses.budget_subcategory_id')
            ->join('budget_categories', 'budget_categories.id', '=', 'budget_subcategories.budget_category_id')
            ->whereIn('budget_expenses.financial_account_id', $ids)
            ->groupBy('budget_expenses.financial_account_id', 'budget_categories.type')
            ->select([
                'budget_expenses.financial_account_id',
                'budget_categories.type',
                DB::raw('SUM(budget_expenses.amount) as total'),
                DB::raw('COUNT(*) as movements'),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $key = $row->type === BudgetCategory::TYPE_INCOME ? 'income' : 'expense';

            $this->add($totals, (int) $row->financial_account_id, $key, $row->total, $row->movements);
        }

        return $this->withTransfers($totals, $ids);
    }

    /**
     * Il saldo di un conto: da dove è partito, più quello che è entrato,
     * meno quello che è uscito.
     *
     * @param  array{income: float, expense: float, count: int}  $movement
     */
    public function balanceOf(FinancialAccount $account, array $movement): float
    {
        return round((float) $account->initial_balance + $movement['income'] - $movement['expense'], 2);
    }

    /**
     * I conti con il loro saldo, come li vedono il budget mensile e il
     * pannello dei movimenti.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listFor(User $owner): array
    {
        $accounts = $owner->financialAccounts()->orderBy('position')->orderBy('name')->get();
        $movements = $this->movementsOf($accounts);

        return $accounts->map(fn (FinancialAccount $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'color' => $account->color,
            'icon' => $account->icon,
            'hidden_from_stats' => $account->hidden_from_stats,
            'excluded_from_stats' => $account->excluded_from_stats,
            'balance' => $this->balanceOf($account, $movements[$account->id] ?? self::NO_MOVEMENTS),
        ])->all();
    }

    /**
     * I trasferimenti non sono spese, ma i saldi li sentono: quello che esce
     * da un conto entra nell'altro.
     *
     * @param  array<int, array{income: float, expense: float, count: int}>  $totals
     * @param  Collection<int, mixed>  $ids
     * @return array<int, array{income: float, expense: float, count: int}>
     */
    private function withTransfers(array $totals, Collection $ids): array
    {
        $sides = [
            'from_financial_account_id' => 'expense',
            'to_financial_account_id' => 'income',
        ];

        foreach ($sides as $column => $key) {
            $rows = DB::table('account_transfers')
                ->whereIn($column, $ids)
                ->groupBy($column)
                ->select([
                    $column.' as account_id',
                    DB::raw('SUM(amount) as total'),
                    DB::raw('COUNT(*) as movements'),
                ])
                ->get();

            foreach ($rows as $row) {
                $this->add($totals, (int) $row->account_id, $key, $row->total, $row->movements);
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, array{income: float, expense: float, count: int}>  $totals
     * @param  'income'|'expense'  $key
     */
    private function add(array &$totals, int $accountId, string $key, mixed $total, mixed $movements): void
    {
        $totals[$accountId] ??= ['income' => 0.0, 'expense' => 0.0, 'count' => 0];
        $totals[$accountId][$key] += round((float) $total, 2);
        $totals[$accountId]['count'] += (int) $movements;
    }
}
