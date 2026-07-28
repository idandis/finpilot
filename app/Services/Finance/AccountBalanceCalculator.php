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
     * Current cash balance keyed per "balance bucket": a linked financial
     * account's id (int) - shared by every card on that account, not just
     * the ones passed in, since an ordinary debit card can draw from the
     * same cash pool - or "card:{id}" for a standalone card with no linked
     * account, whose balance is just its own transactions starting from
     * zero (there's no initial_balance to draw from without an account).
     * This backs the "saldo conto" figure shown alongside investment totals
     * on the Investments page and the Dashboard, and works whether or not a
     * card has a linked account.
     *
     * @param  Collection<int, Card>  $cards
     * @return array<int|string, float>
     */
    public function calculate(Collection $cards): array
    {
        $accountIds = $cards->pluck('financial_account_id')->filter()->unique()->values();
        $standaloneCardIds = $cards->whereNull('financial_account_id')->pluck('id')->values();

        $balances = [];

        if ($accountIds->isNotEmpty()) {
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
        }

        foreach ($standaloneCardIds as $cardId) {
            $balances["card:{$cardId}"] = 0.0;
        }

        if ($standaloneCardIds->isNotEmpty()) {
            Transaction::query()
                ->whereIn('card_id', $standaloneCardIds)
                ->select('card_id', 'direction', DB::raw('SUM(amount) as total'))
                ->groupBy('card_id', 'direction')
                ->get()
                ->each(function ($row) use (&$balances) {
                    $delta = $row->direction === 'income' ? (float) $row->total : -(float) $row->total;
                    $balances["card:{$row->card_id}"] += $delta;
                });
        }

        return $balances;
    }

    /**
     * Convenience wrapper for the common case: total balance across every
     * card passed in, whether or not it has a linked account - null only
     * when there are no cards at all.
     *
     * @param  Collection<int, Card>  $cards
     */
    public function totalFor(Collection $cards): ?float
    {
        if ($cards->isEmpty()) {
            return null;
        }

        return round(array_sum($this->calculate($cards)), 2);
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
     * @return array<string, float|null> keyed by the given date strings, null for every date only when there are no cards at all
     */
    public function historyAsOf(Collection $cards, Collection $dates): array
    {
        if ($cards->isEmpty()) {
            return $dates->mapWithKeys(fn (string $date) => [$date => null])->all();
        }

        $accountIds = $cards->pluck('financial_account_id')->filter()->unique()->values();
        $standaloneCardIds = $cards->whereNull('financial_account_id')->pluck('id')->values();

        $running = [];

        if ($accountIds->isNotEmpty()) {
            $running = FinancialAccount::query()
                ->whereIn('id', $accountIds)
                ->pluck('initial_balance', 'id')
                ->map(fn ($initialBalance) => (float) $initialBalance)
                ->all();
        }

        foreach ($standaloneCardIds as $cardId) {
            $running["card:{$cardId}"] = 0.0;
        }

        $cardKeys = Card::query()
            ->whereIn('financial_account_id', $accountIds)
            ->pluck('financial_account_id', 'id')
            ->all();

        foreach ($standaloneCardIds as $cardId) {
            $cardKeys[$cardId] = "card:{$cardId}";
        }

        $transactions = Transaction::query()
            ->whereIn('card_id', array_keys($cardKeys))
            ->orderBy('transaction_date')
            ->get(['card_id', 'transaction_date', 'amount', 'direction'])
            ->all();

        $cursor = 0;
        $result = [];

        foreach ($dates as $date) {
            $asOf = Carbon::parse($date)->endOfDay();

            while ($cursor < count($transactions) && Carbon::parse($transactions[$cursor]->transaction_date)->lte($asOf)) {
                $transaction = $transactions[$cursor];
                $key = $cardKeys[$transaction->card_id] ?? null;

                if ($key !== null) {
                    $delta = $transaction->direction === 'income' ? (float) $transaction->amount : -(float) $transaction->amount;
                    $running[$key] = ($running[$key] ?? 0.0) + $delta;
                }

                $cursor++;
            }

            $result[$date] = round(array_sum($running), 2);
        }

        return $result;
    }
}
