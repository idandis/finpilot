<?php

namespace App\Services\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetMonthClosure;
use App\Models\BalanceSheetProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BalanceSheetMonthCloseService
{
    public function __construct(
        private readonly BalanceSheetTotalsCalculator $calculator,
    ) {}

    public function close(User $user): BalanceSheetMonthClosure
    {
        return DB::transaction(function () use ($user) {
            $profile = BalanceSheetProfile::forUser($user);

            $incomeTotal = $this->calculator->monthlyIncome($user);
            $expenseTotal = $this->calculator->monthlyExpense($user);
            $cashFlow = $incomeTotal - $expenseTotal;

            $cashBalanceBefore = (float) $profile->cash_balance;
            $profile->cash_balance = $cashBalanceBefore + $cashFlow;
            $profile->closed_months++;
            $profile->save();

            // Ogni effetto viene registrato prima di applicarlo: è quello che
            // rende la chiusura annullabile senza indovinare nulla.
            $effects = [
                'cash_balance_before' => $cashBalanceBefore,
                ...$this->payDownLiabilities($user),
                'one_time_deactivated' => $this->deactivateOneTimeEntries($user),
            ];

            $closedAt = now();

            return $user->balanceSheetMonthClosures()->create([
                'year' => $closedAt->year,
                'month' => $closedAt->month,
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'cash_flow' => $cashFlow,
                'cash_balance_after' => $profile->cash_balance,
                'effects' => $effects,
            ]);
        });
    }

    /**
     * Rimette le cose come stavano prima della chiusura: liquidità, debiti e
     * voci disattivate. Solo l'ultima chiusura è annullabile, altrimenti gli
     * effetti delle successive resterebbero appesi nel vuoto.
     */
    public function reopen(User $user, BalanceSheetMonthClosure $closure): void
    {
        DB::transaction(function () use ($user, $closure) {
            $profile = BalanceSheetProfile::forUser($user);

            // Sottrarre il flusso invece di ripristinare il saldo salvato
            // preserva le modifiche fatte alla liquidità dopo la chiusura.
            $profile->cash_balance = (float) $profile->cash_balance - (float) $closure->cash_flow;
            $profile->closed_months = max(0, $profile->closed_months - 1);
            $profile->save();

            $effects = $closure->effects ?? [];

            foreach ($effects['liabilities'] ?? [] as $liability) {
                BalanceSheetEntry::query()
                    ->where('user_id', $user->id)
                    ->where('id', $liability['id'])
                    ->update(['amount' => $liability['amount'], 'active' => $liability['active']]);
            }

            $reactivate = [
                ...$effects['expenses_deactivated'] ?? [],
                ...$effects['one_time_deactivated'] ?? [],
            ];

            if ($reactivate !== []) {
                BalanceSheetEntry::query()
                    ->where('user_id', $user->id)
                    ->whereIn('id', $reactivate)
                    ->update(['active' => true]);
            }

            $closure->delete();
        });
    }

    /**
     * Every active expense linked to a liability (e.g. a mortgage installment)
     * reduces that liability's residual amount. Once it reaches zero, the
     * liability is extinguished and its installment stops being charged.
     *
     * @return array{liabilities: array<int, array{id: int, amount: string|null, active: bool}>, expenses_deactivated: array<int, int>}
     */
    private function payDownLiabilities(User $user): array
    {
        $touchedLiabilities = [];
        $deactivatedExpenses = [];

        BalanceSheetEntry::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('active', true)
            ->whereNotNull('linked_liability_id')
            ->with('linkedLiabilityEntry')
            ->get()
            ->each(function (BalanceSheetEntry $expense) use (&$touchedLiabilities, &$deactivatedExpenses) {
                $liability = $expense->linkedLiabilityEntry;

                if (! $liability || ! $liability->active) {
                    return;
                }

                $touchedLiabilities[] = [
                    'id' => $liability->id,
                    'amount' => $liability->amount,
                    'active' => $liability->active,
                ];

                $remaining = max(0.0, (float) $liability->amount - (float) $expense->amount);
                $liability->update(['amount' => $remaining, 'active' => $remaining > 0]);

                if ($remaining <= 0) {
                    $expense->update(['active' => false]);
                    $deactivatedExpenses[] = $expense->id;
                }
            });

        return [
            'liabilities' => $touchedLiabilities,
            'expenses_deactivated' => $deactivatedExpenses,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function deactivateOneTimeEntries(User $user): array
    {
        $entries = BalanceSheetEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('type', ['income', 'expense'])
            ->where('frequency', 'one_time')
            ->where('active', true)
            ->pluck('id')
            ->all();

        if ($entries !== []) {
            BalanceSheetEntry::query()->whereIn('id', $entries)->update(['active' => false]);
        }

        return $entries;
    }
}
