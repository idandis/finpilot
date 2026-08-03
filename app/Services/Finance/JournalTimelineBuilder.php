<?php

namespace App\Services\Finance;

use App\Models\InvestmentJournalEntry;
use App\Models\InvestmentReview;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Builds the Journal tab's unified timeline for one Investment: every buy,
 * increase, reduce and sell is derived straight from the ISIN's own
 * Transaction rows (same average-cost bookkeeping as
 * InvestmentPositionCalculator, just replayed event-by-event instead of
 * collapsed into a single open/closed summary), interleaved with the user's
 * own manual journal notes and quarterly reviews. Nothing is stored
 * redundantly - the transaction/review events are computed fresh every time
 * from data that already exists elsewhere.
 */
class JournalTimelineBuilder
{
    private const EPSILON = 0.000001;

    /**
     * @param  Collection<int, Transaction>  $transactions  all of this ISIN's trade rows, any order
     * @param  Collection<int, InvestmentJournalEntry>  $journalEntries
     * @param  Collection<int, InvestmentReview>  $reviews
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $transactions, Collection $journalEntries, Collection $reviews): array
    {
        $events = [
            ...$this->transactionEvents($transactions, $journalEntries),
            ...$this->noteEvents($journalEntries),
            ...$this->reviewEvents($reviews),
        ];

        usort($events, fn (array $a, array $b) => $b['date'] <=> $a['date']);

        return $events;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @param  Collection<int, InvestmentJournalEntry>  $journalEntries
     * @return array<int, array<string, mixed>>
     */
    private function transactionEvents(Collection $transactions, Collection $journalEntries): array
    {
        $notesByTransaction = $journalEntries
            ->whereNotNull('transaction_id')
            ->groupBy('transaction_id');

        $trades = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isin !== null && TradeDescription::isTradeRow($transaction->description))
            ->sortBy('transaction_date')
            ->values();

        $quantity = 0.0;
        $events = [];

        foreach ($trades as $trade) {
            $tradeQuantity = (float) $trade->quantity;
            $increases = TradeDescription::increasesPosition($trade->description);
            $before = $quantity;

            if ($increases) {
                $quantity += $tradeQuantity;
                $type = $before <= self::EPSILON ? 'buy' : 'increase';
            } else {
                $quantity = max(0.0, $quantity - $tradeQuantity);
                $type = $quantity <= self::EPSILON ? 'sell' : 'reduce';
            }

            $events[] = [
                'type' => $type,
                'date' => $trade->transaction_date instanceof \DateTimeInterface
                    ? $trade->transaction_date->format('Y-m-d')
                    : (string) $trade->transaction_date,
                'description' => $trade->description,
                'amount' => (float) $trade->amount,
                'quantity' => $tradeQuantity,
                'transaction_id' => $trade->id,
                'notes' => $notesByTransaction->get($trade->id, collect())
                    ->map(fn (InvestmentJournalEntry $entry) => $entry->note)
                    ->values()
                    ->all(),
            ];
        }

        $dividends = $transactions
            ->filter(fn (Transaction $transaction) => TradeDescription::parseDividend($transaction->description)['isin'] !== null)
            ->map(fn (Transaction $transaction) => [
                'type' => 'dividend',
                'date' => $transaction->transaction_date instanceof \DateTimeInterface
                    ? $transaction->transaction_date->format('Y-m-d')
                    : (string) $transaction->transaction_date,
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'quantity' => null,
                'transaction_id' => $transaction->id,
                'notes' => [],
            ])
            ->values()
            ->all();

        return [...$events, ...$dividends];
    }

    /**
     * @param  Collection<int, InvestmentJournalEntry>  $journalEntries
     * @return array<int, array<string, mixed>>
     */
    private function noteEvents(Collection $journalEntries): array
    {
        return $journalEntries
            ->whereNull('transaction_id')
            ->map(fn (InvestmentJournalEntry $entry) => [
                'type' => 'note',
                'date' => $entry->occurred_at->format('Y-m-d'),
                'description' => $entry->note,
                'amount' => null,
                'quantity' => null,
                'transaction_id' => null,
                'journal_entry_id' => $entry->id,
                'notes' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InvestmentReview>  $reviews
     * @return array<int, array<string, mixed>>
     */
    private function reviewEvents(Collection $reviews): array
    {
        return $reviews
            ->map(fn (InvestmentReview $review) => [
                'type' => 'review',
                'date' => $review->review_date->format('Y-m-d'),
                'description' => $review->note,
                'decision' => $review->decision,
                'score_before' => $review->score_before,
                'score_after' => $review->score_after,
                'amount' => null,
                'quantity' => null,
                'transaction_id' => null,
                'review_id' => $review->id,
                'notes' => [],
            ])
            ->values()
            ->all();
    }
}
