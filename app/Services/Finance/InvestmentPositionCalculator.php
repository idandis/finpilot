<?php

namespace App\Services\Finance;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class InvestmentPositionCalculator
{
    /**
     * Below this, a position's remaining quantity is treated as zero -
     * trade quantities are decimals (fractional shares, crypto), so exact
     * equality to 0.0 can't be relied on after repeated subtraction.
     */
    private const EPSILON = 0.000001;

    public function __construct(
        private readonly InstrumentPriceRepository $priceRepository,
        private readonly ExchangeRateRepository $rateRepository,
    ) {}

    /**
     * Turn a user's buy/sell/savings-plan transactions into a position per
     * instrument (ISIN), using the average-cost method: every buy raises
     * the average cost, every sell realizes gain/loss against that average
     * and reduces it proportionally. A "Cancellation" row reverses the
     * trade it corrects (a cancelled buy behaves like a sell of what was
     * never really bought, and vice versa).
     *
     * `realized_gain` accumulates proceeds minus the cost basis of whatever
     * was actually sold, trade by trade - so a position that's still open
     * today but has had a partial sell along the way still reports what
     * that partial sell actually earned, instead of hiding it until (or
     * unless) the position fully closes.
     *
     * Open positions are additionally enriched with the latest cached
     * market price (see InstrumentPriceRepository) when one is available,
     * so cost-basis reconciliation and market valuation coexist: the
     * market fields are simply null when no price is cached yet. A price
     * denominated in a currency other than EUR is converted using the
     * cached exchange rate (see ExchangeRateRepository) when one is
     * available; otherwise it's left null rather than showing an
     * unconverted, misleading figure.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array{open: array<int, array<string, mixed>>, closed: array<int, array<string, mixed>>}
     */
    public function calculate(Collection $transactions): array
    {
        $byIsin = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isin !== null && TradeDescription::isTradeRow($transaction->description))
            ->sortBy('transaction_date')
            ->groupBy('isin');

        $open = [];
        $closed = [];

        foreach ($byIsin as $isin => $trades) {
            $quantity = 0.0;
            $costBasis = 0.0;
            $totalInvested = 0.0;
            $totalReceived = 0.0;
            $realizedGain = 0.0;
            $openedAt = null;
            $closedAt = null;
            $name = null;

            foreach ($trades as $trade) {
                $name ??= $this->instrumentName($trade->description);
                $tradeQuantity = (float) $trade->quantity;
                $amount = (float) $trade->amount;

                if (TradeDescription::increasesPosition($trade->description)) {
                    $openedAt ??= $trade->transaction_date;
                    $quantity += $tradeQuantity;
                    $costBasis += $amount;
                    $totalInvested += $amount;

                    continue;
                }

                $averageCost = $quantity > self::EPSILON ? $costBasis / $quantity : 0.0;
                $soldQuantity = min($tradeQuantity, $quantity);
                $costOfSoldShares = $averageCost * $soldQuantity;
                $costBasis -= $costOfSoldShares;
                $quantity -= $soldQuantity;
                $totalReceived += $amount;
                $realizedGain += $amount - $costOfSoldShares;

                if ($quantity <= self::EPSILON) {
                    $closedAt = $trade->transaction_date;
                }
            }

            if ($quantity > self::EPSILON) {
                $marketPrice = $this->priceRepository->getCachedPrice($isin);
                $rawPrice = $marketPrice?->last_price !== null ? (float) $marketPrice->last_price : null;
                $currentPrice = $this->convertToEur($marketPrice?->last_price, $marketPrice?->currency);

                $marketValue = $currentPrice !== null ? round($currentPrice * $quantity, 2) : null;
                $marketValueOriginal = $rawPrice !== null ? round($rawPrice * $quantity, 2) : null;
                $unrealizedGain = $marketValue !== null ? round($marketValue - $costBasis, 2) : null;
                $unrealizedGainPercent = ($marketValue !== null && $costBasis > self::EPSILON)
                    ? round((($marketValue - $costBasis) / $costBasis) * 100, 2)
                    : null;

                $open[] = [
                    'isin' => $isin,
                    'name' => $name,
                    'is_crypto' => TradeDescription::isCrypto($isin),
                    'quantity' => round($quantity, 8),
                    'invested' => round($costBasis, 2),
                    'average_price' => round($costBasis / $quantity, 4),
                    'opened_at' => $openedAt?->format('Y-m-d'),
                    'current_price' => $currentPrice,
                    'market_value' => $marketValue,
                    'unrealized_gain' => $unrealizedGain,
                    'unrealized_gain_percent' => $unrealizedGainPercent,
                    'price_date' => $marketPrice?->price_date?->format('Y-m-d'),
                    'price_currency' => $marketPrice?->currency,
                    'current_price_original' => $rawPrice,
                    'market_value_original' => $marketValueOriginal,
                    'realized_gain' => round($realizedGain, 2),
                ];
            } elseif ($totalInvested > 0) {
                $closed[] = [
                    'isin' => $isin,
                    'name' => $name,
                    'is_crypto' => TradeDescription::isCrypto($isin),
                    'invested' => round($totalInvested, 2),
                    'received' => round($totalReceived, 2),
                    'realized_gain' => round($realizedGain, 2),
                    'opened_at' => $openedAt?->format('Y-m-d'),
                    'closed_at' => $closedAt?->format('Y-m-d'),
                ];
            }
        }

        return [
            // Crypto sorted to the bottom within each group (never priced by
            // EODHD, so it's the least actionable information in the table)
            // - otherwise unchanged from the existing ordering.
            'open' => collect($open)->sortBy([['is_crypto', 'asc'], ['invested', 'desc']])->values()->all(),
            'closed' => collect($closed)->sortBy([['is_crypto', 'asc'], ['closed_at', 'desc']])->values()->all(),
        ];
    }

    /**
     * A raw cached price is only usable once expressed in EUR: if it's
     * already EUR, use it as-is; otherwise convert it using the cached
     * exchange rate for that currency, if one has been fetched. Returns
     * null whenever there's nothing to show yet, rather than a figure that
     * silently mixes currencies.
     */
    private function convertToEur(?string $rawPrice, ?string $currency): ?float
    {
        if ($rawPrice === null || $currency === null) {
            return null;
        }

        if ($currency === 'EUR') {
            return (float) $rawPrice;
        }

        $rate = $this->rateRepository->getCachedRate($currency);

        if ($rate?->rate_to_eur === null) {
            return null;
        }

        return round((float) $rawPrice * (float) $rate->rate_to_eur, 6);
    }

    private function instrumentName(string $description): string
    {
        return TradeDescription::parseTrade($description)['name'] ?? $description;
    }
}
