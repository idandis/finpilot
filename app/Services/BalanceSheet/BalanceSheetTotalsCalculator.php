<?php

namespace App\Services\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetProfile;
use App\Models\User;

class BalanceSheetTotalsCalculator
{
    public function monthlyIncome(User $user): float
    {
        return $this->sum($user, 'income', 'amount');
    }

    public function monthlyExpense(User $user): float
    {
        return $this->sum($user, 'expense', 'amount');
    }

    public function totalAssets(User $user): float
    {
        return $this->sum($user, 'asset', 'amount');
    }

    public function totalLiabilities(User $user): float
    {
        return $this->sum($user, 'liability', 'amount');
    }

    public function hoursConsumed(User $user): float
    {
        return $this->sum($user, 'time', 'hours_per_month', 'consumes');
    }

    public function hoursFreed(User $user): float
    {
        return $this->sum($user, 'time', 'hours_per_month', 'frees');
    }

    /**
     * @return array{cash_balance: float, monthly_income: float, monthly_expense: float, cash_flow: float, total_assets: float, total_liabilities: float, net_worth: float, hours_consumed: float, hours_freed: float, free_time: float, hourly_value: float|null}
     */
    public function overview(User $user, BalanceSheetProfile $profile): array
    {
        $income = $this->monthlyIncome($user);
        $expense = $this->monthlyExpense($user);
        $assets = $this->totalAssets($user);
        $liabilities = $this->totalLiabilities($user);
        $hoursConsumed = $this->hoursConsumed($user);
        $hoursFreed = $this->hoursFreed($user);
        $cashBalance = (float) $profile->cash_balance;

        return [
            'cash_balance' => $cashBalance,
            'monthly_income' => $income,
            'monthly_expense' => $expense,
            'cash_flow' => $income - $expense,
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'net_worth' => $assets - $liabilities + $cashBalance,
            'hours_consumed' => $hoursConsumed,
            'hours_freed' => $hoursFreed,
            'free_time' => (float) $profile->base_monthly_hours + $hoursFreed - $hoursConsumed,
            'hourly_value' => $hoursConsumed > 0 ? round($income / $hoursConsumed, 2) : null,
        ];
    }

    private function sum(User $user, string $type, string $column, ?string $timeKind = null): float
    {
        return (float) BalanceSheetEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('active', true)
            ->when($timeKind !== null, fn ($query) => $query->where('time_kind', $timeKind))
            ->sum($column);
    }
}
