<?php

namespace App\Services\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountBalanceCalculator
{
    /**
     * Current cash balance per financial account linked to the given cards,
     * keyed by financial_account_id: initial_balance plus every
     * income/expense transaction ever posted against ANY card on that
     * account - not just the cards passed in, since a single account can
     * also have an ordinary debit card whose spending affects the same cash
     * pool. This backs the "saldo conto" figure shown alongside investment
     * totals on the Investments page and the Dashboard.
     *
     * @param  Collection<int, Card>  $cards
     * @return array<int, float>
     */
    public function calculate(Collection $cards): array
    {
        $accountIds = $cards->pluck('financial_account_id')->filter()->unique()->values();

        if ($accountIds->isEmpty()) {
            return [];
        }

        $balances = FinancialAccount::query()
            ->whereIn('id', $accountIds)
            ->pluck('initial_balance', 'id')
            ->map(fn ($initialBalance) => (float) $initialBalance)
            ->all();

        $cardToAccount = Card::query()
            ->whereIn('financial_account_id', $accountIds)
            ->pluck('financial_account_id', 'id');

        Transaction::query()
            ->whereIn('card_id', $cardToAccount->keys())
            ->select('card_id', 'direction', DB::raw('SUM(amount) as total'))
            ->groupBy('card_id', 'direction')
            ->get()
            ->each(function ($row) use (&$balances, $cardToAccount) {
                $accountId = $cardToAccount->get($row->card_id);

                if ($accountId === null) {
                    return;
                }

                $delta = $row->direction === 'income' ? (float) $row->total : -(float) $row->total;
                $balances[$accountId] = ($balances[$accountId] ?? 0.0) + $delta;
            });

        return $balances;
    }

    /**
     * Convenience wrapper for the common case: total balance across every
     * distinct account linked to the given cards (e.g. all of a user's
     * investment cards combined), null when none of them have a linked
     * account at all.
     *
     * @param  Collection<int, Card>  $cards
     */
    public function totalFor(Collection $cards): ?float
    {
        $accountIds = $cards->pluck('financial_account_id')->filter()->unique();

        if ($accountIds->isEmpty()) {
            return null;
        }

        $balances = $this->calculate($cards);

        return $accountIds->sum(fn (int $accountId) => $balances[$accountId] ?? 0.0);
    }

    /**
     * The same total balance as `totalFor()`, but reconstructed as of each
     * given date instead of just "now" - for the wealth-over-time chart on
     * /investments. Walks every transaction on the linked account(s) once,
     * in date order, advancing a running total past each snapshot date in
     * turn (same cumulative-cursor approach as PortfolioValueHistoryCalculator
     * uses for "invested"), rather than re-querying per date.
     *
     * @param  Collection<int, Card>  $cards
     * @param  Collection<int, string>  $dates  ascending 'Y-m-d' dates
     * @return array<string, float|null> keyed by the given date strings, null when no account is linked
     */
    public function historyAsOf(Collection $cards, Collection $dates): array
    {
        $accountIds = $cards->pluck('financial_account_id')->filter()->unique()->values();

        if ($accountIds->isEmpty()) {
            return $dates->mapWithKeys(fn (string $date) => [$date => null])->all();
        }

        $running = FinancialAccount::query()
            ->whereIn('id', $accountIds)
            ->pluck('initial_balance', 'id')
            ->map(fn ($initialBalance) => (float) $initialBalance)
            ->all();

        $cardToAccount = Card::query()
            ->whereIn('financial_account_id', $accountIds)
            ->pluck('financial_account_id', 'id');

        $transactions = Transaction::query()
            ->whereIn('card_id', $cardToAccount->keys())
            ->orderBy('transaction_date')
            ->get(['card_id', 'transaction_date', 'amount', 'direction'])
            ->all();

        $cursor = 0;
        $result = [];

        foreach ($dates as $date) {
            $asOf = Carbon::parse($date)->endOfDay();

            while ($cursor < count($transactions) && Carbon::parse($transactions[$cursor]->transaction_date)->lte($asOf)) {
                $transaction = $transactions[$cursor];
                $accountId = $cardToAccount->get($transaction->card_id);

                if ($accountId !== null) {
                    $delta = $transaction->direction === 'income' ? (float) $transaction->amount : -(float) $transaction->amount;
                    $running[$accountId] = ($running[$accountId] ?? 0.0) + $delta;
                }

                $cursor++;
            }

            $result[$date] = round(array_sum($running), 2);
        }

        return $result;
    }
}
