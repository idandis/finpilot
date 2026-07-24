<?php

namespace App\Services\Finance;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reconstructs how many units of an instrument were held at any point in
 * the past, from its trade rows - the same accumulation rule already used
 * by InvestmentPositionCalculator (quantity only, no cost-basis), applied
 * at arbitrary past dates instead of just the latest one.
 */
class InstrumentQuantityTimeline
{
    /**
     * @param  Collection<int, Transaction>  $trades
     * @return array<int, array{date: CarbonInterface, quantity: float}>
     */
    public static function build(Collection $trades): array
    {
        $quantity = 0.0;
        $checkpoints = [];

        foreach ($trades->sortBy('transaction_date') as $trade) {
            $tradeQuantity = (float) $trade->quantity;
            $quantity = TradeDescription::increasesPosition($trade->description)
                ? $quantity + $tradeQuantity
                : max($quantity - min($tradeQuantity, $quantity), 0.0);

            $checkpoints[] = ['date' => $trade->transaction_date, 'quantity' => $quantity];
        }

        return $checkpoints;
    }

    /**
     * @param  array<int, array{date: CarbonInterface, quantity: float}>  $checkpoints
     */
    public static function quantityAsOf(array $checkpoints, CarbonInterface $asOf): float
    {
        $quantity = 0.0;

        foreach ($checkpoints as $checkpoint) {
            if ($checkpoint['date']->gt($asOf)) {
                break;
            }

            $quantity = $checkpoint['quantity'];
        }

        return $quantity;
    }
}
