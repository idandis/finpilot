<?php

namespace App\Services\Finance;

use App\Models\ExchangeRateHistory;
use App\Models\InstrumentPriceHistory;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a weekly time series of a card's investment "invested" (net cash
 * committed so far) versus "market_value" (mark-to-market, EUR), for the
 * portfolio history chart on /investments.
 *
 * "invested" is the cumulative net cash flow (buys/fees minus sells/
 * dividends/cashback), not a per-ISIN cost basis: a fully-sold instrument
 * must not make its past capital disappear from the chart, which a
 * cost-basis reconstruction would do. "market_value" does need a per-ISIN
 * reconstruction, using cached historical prices/rates - and degrades
 * silently (never a fabricated number) when history isn't available yet
 * for a given week.
 *
 * Cash flow tied to an instrument EODHD can never resolve (currently only
 * crypto pseudo-ISINs, flagged by `resolution_failed` once a resolution
 * attempt has actually failed) is excluded from "invested" too, so both
 * lines compare the same priceable subset of the portfolio - its holdings
 * are reported separately instead (see `unpriced_positions`).
 */
class PortfolioValueHistoryCalculator
{
    private const EPSILON = 0.000001;

    public function __construct(
        private readonly InstrumentPriceRepository $priceRepository,
        private readonly InstrumentPriceHistoryRepository $priceHistoryRepository,
        private readonly ExchangeRateHistoryRepository $rateHistoryRepository,
    ) {}

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{points: array<int, array{date: string, invested: float, market_value: float|null}>, market_data_since: string|null, unpriced_positions: array<int, array{isin: string, name: string, quantity: float, invested: float}>}
     */
    public function calculate(Collection $transactions): array
    {
        if ($transactions->isEmpty()) {
            return ['points' => [], 'market_data_since' => null, 'unpriced_positions' => []];
        }

        $tradesByIsin = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isin !== null && TradeDescription::isTradeRow($transaction->description))
            ->groupBy('isin');

        $timelines = $tradesByIsin->map(fn (Collection $trades) => InstrumentQuantityTimeline::build($trades));

        $priceRecordByIsin = $timelines->keys()->mapWithKeys(
            fn (string $isin) => [$isin => $this->priceRepository->getCachedPrice($isin)]
        );

        $currencyByIsin = $priceRecordByIsin->map(fn ($record) => $record?->currency);

        $unresolvableIsins = $priceRecordByIsin
            ->filter(fn ($record) => $record?->resolution_failed === true)
            ->keys();

        $sortedCash = $transactions
            ->reject(fn (Transaction $transaction) => $transaction->isin !== null && $unresolvableIsins->contains($transaction->isin))
            ->sortBy('transaction_date')
            ->values();

        $priceHistoryByIsin = $timelines->keys()->mapWithKeys(
            fn (string $isin) => [$isin => $this->priceHistoryRepository->historyFor($isin)->all()]
        );

        $rateHistoryByCurrency = $currencyByIsin->values()
            ->filter(fn (?string $currency) => $currency !== null && $currency !== 'EUR')
            ->unique()
            ->mapWithKeys(fn (string $currency) => [$currency => $this->rateHistoryRepository->historyFor($currency)->all()]);

        $priceCursors = $timelines->keys()->mapWithKeys(fn (string $isin) => [$isin => -1])->all();
        $rateCursors = $rateHistoryByCurrency->keys()->mapWithKeys(fn (string $currency) => [$currency => -1])->all();

        // From the full, unfiltered set - so the timeline still spans the
        // user's whole investing history even when every early transaction
        // turns out to be an excluded, unresolvable ISIN (e.g. crypto-only
        // activity so far).
        $firstDate = CarbonImmutable::parse($transactions->min('transaction_date'))->startOfDay();
        $today = CarbonImmutable::now()->startOfDay();

        $cashArray = $sortedCash->all();
        $cashCursor = 0;
        $invested = 0.0;

        $points = [];
        $marketDataSince = null;

        foreach ($this->weeklySnapshotDates($firstDate, $today) as $weekEnd) {
            while ($cashCursor < count($cashArray) && CarbonImmutable::parse($cashArray[$cashCursor]->transaction_date)->lte($weekEnd)) {
                $transaction = $cashArray[$cashCursor];
                $invested += $transaction->direction === 'expense' ? (float) $transaction->amount : -(float) $transaction->amount;
                $cashCursor++;
            }

            $anyHeld = false;
            $pricedValue = 0.0;
            $pricedCount = 0;

            foreach ($timelines as $isin => $checkpoints) {
                $quantity = InstrumentQuantityTimeline::quantityAsOf($checkpoints, $weekEnd);

                if ($quantity <= self::EPSILON) {
                    continue;
                }

                $anyHeld = true;

                $priceEur = $this->eurPriceAsOf(
                    $isin,
                    $currencyByIsin[$isin],
                    $weekEnd,
                    $priceHistoryByIsin,
                    $rateHistoryByCurrency,
                    $priceCursors,
                    $rateCursors,
                );

                if ($priceEur === null) {
                    continue;
                }

                $pricedValue += $priceEur * $quantity;
                $pricedCount++;
            }

            $marketValue = match (true) {
                ! $anyHeld => 0.0,
                $pricedCount === 0 => null,
                default => round($pricedValue, 2),
            };

            if ($marketDataSince === null && $anyHeld && $marketValue !== null) {
                $marketDataSince = $weekEnd->format('Y-m-d');
            }

            $points[] = [
                'date' => $weekEnd->format('Y-m-d'),
                'invested' => round($invested, 2),
                'market_value' => $marketValue,
            ];
        }

        $unpricedPositions = $this->unpricedPositions($timelines, $priceHistoryByIsin, $tradesByIsin, $today);

        return ['points' => $points, 'market_data_since' => $marketDataSince, 'unpriced_positions' => $unpricedPositions];
    }

    /**
     * Instruments still held today with zero price history at all - e.g. a
     * crypto ISIN EODHD can never resolve, or a real one not backfilled yet.
     * These are excluded from both "invested" and "market_value" above
     * (never a fabricated number), so their own holdings - quantity and net
     * cash committed - are reported here instead, for the chart to show
     * separately from the two priced lines.
     *
     * @param  Collection<string, array<int, array{date: CarbonInterface, quantity: float}>>  $timelines
     * @param  Collection<string, array<int, InstrumentPriceHistory>>  $priceHistoryByIsin
     * @param  Collection<string, Collection<int, Transaction>>  $tradesByIsin
     * @return array<int, array{isin: string, name: string, quantity: float, invested: float}>
     */
    private function unpricedPositions(Collection $timelines, Collection $priceHistoryByIsin, Collection $tradesByIsin, CarbonInterface $today): array
    {
        $unpriced = [];

        foreach ($timelines as $isin => $checkpoints) {
            $quantity = InstrumentQuantityTimeline::quantityAsOf($checkpoints, $today);

            if ($quantity <= self::EPSILON) {
                continue;
            }

            if (count($priceHistoryByIsin[$isin] ?? []) > 0) {
                continue;
            }

            $trades = $tradesByIsin[$isin];
            $name = TradeDescription::parseTrade($trades->first()->description)['name'] ?? $isin;
            $invested = $trades->sum(fn (Transaction $trade) => $trade->direction === 'expense' ? (float) $trade->amount : -(float) $trade->amount);

            $unpriced[] = [
                'isin' => $isin,
                'name' => $name,
                'quantity' => round($quantity, 8),
                'invested' => round($invested, 2),
            ];
        }

        return $unpriced;
    }

    /**
     * @param  Collection<string, array<int, InstrumentPriceHistory>>  $priceHistoryByIsin
     * @param  Collection<string, array<int, ExchangeRateHistory>>  $rateHistoryByCurrency
     * @param  array<string, int>  $priceCursors
     * @param  array<string, int>  $rateCursors
     */
    private function eurPriceAsOf(
        string $isin,
        ?string $currency,
        CarbonInterface $asOf,
        Collection $priceHistoryByIsin,
        Collection $rateHistoryByCurrency,
        array &$priceCursors,
        array &$rateCursors,
    ): ?float {
        $history = $priceHistoryByIsin[$isin] ?? [];
        $priceCursors[$isin] = $this->advanceCursor($history, $priceCursors[$isin] ?? -1, $asOf, fn ($row) => $row->price_date);

        if ($priceCursors[$isin] < 0) {
            return null;
        }

        $rawPrice = (float) $history[$priceCursors[$isin]]->close_price;

        if ($currency === null || $currency === 'EUR') {
            return $rawPrice;
        }

        $rateHistory = $rateHistoryByCurrency[$currency] ?? [];
        $rateCursors[$currency] = $this->advanceCursor($rateHistory, $rateCursors[$currency] ?? -1, $asOf, fn ($row) => $row->rate_date);

        if ($rateCursors[$currency] < 0) {
            return null;
        }

        $rate = (float) $rateHistory[$rateCursors[$currency]]->rate_to_eur;

        return round($rawPrice * $rate, 6);
    }

    /**
     * Advances a cursor over an ascending, preloaded history array to the
     * last row on or before $asOf. Snapshot dates are visited in ascending
     * order, so the cursor only ever moves forward - no query per week.
     *
     * @param  array<int, mixed>  $rows
     * @param  callable(mixed): CarbonInterface  $dateOf
     */
    private function advanceCursor(array $rows, int $cursor, CarbonInterface $asOf, callable $dateOf): int
    {
        if (empty($rows)) {
            return -1;
        }

        $next = $cursor;

        while ($next + 1 < count($rows) && $dateOf($rows[$next + 1])->lte($asOf)) {
            $next++;
        }

        if ($next < 0 || $dateOf($rows[$next])->gt($asOf)) {
            return -1;
        }

        return $next;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function weeklySnapshotDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        $cursor = $from->startOfWeek();

        while ($cursor->lte($to)) {
            $dates[] = $cursor;
            $cursor = $cursor->addWeek();
        }

        if (empty($dates) || ! end($dates)->isSameDay($to)) {
            $dates[] = $to;
        }

        return $dates;
    }
}
