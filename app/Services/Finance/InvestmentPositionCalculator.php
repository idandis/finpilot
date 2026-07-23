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

    /**
     * Turn a user's buy/sell/savings-plan transactions into a position per
     * instrument (ISIN), using the average-cost method: every buy raises
     * the average cost, every sell realizes gain/loss against that average
     * and reduces it proportionally. A "Cancellation" row reverses the
     * trade it corrects (a cancelled buy behaves like a sell of what was
     * never really bought, and vice versa).
     *
     * This is a cash-flow reconciliation, not a live valuation: open
     * positions show what you paid for what you still hold, not what it's
     * worth today.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array{open: array<int, array<string, mixed>>, closed: array<int, array<string, mixed>>}
     */
    public function calculate(Collection $transactions): array
    {
        $byIsin = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isin !== null)
            ->sortBy('transaction_date')
            ->groupBy('isin');

        $open = [];
        $closed = [];

        foreach ($byIsin as $isin => $trades) {
            $quantity = 0.0;
            $costBasis = 0.0;
            $totalInvested = 0.0;
            $totalReceived = 0.0;
            $openedAt = null;
            $closedAt = null;
            $name = null;

            foreach ($trades as $trade) {
                $name ??= $this->instrumentName($trade->description);
                $tradeQuantity = (float) $trade->quantity;
                $amount = (float) $trade->amount;

                if ($this->increasesPosition($trade->description)) {
                    $openedAt ??= $trade->transaction_date;
                    $quantity += $tradeQuantity;
                    $costBasis += $amount;
                    $totalInvested += $amount;

                    continue;
                }

                $averageCost = $quantity > self::EPSILON ? $costBasis / $quantity : 0.0;
                $soldQuantity = min($tradeQuantity, $quantity);
                $costBasis -= $averageCost * $soldQuantity;
                $quantity -= $soldQuantity;
                $totalReceived += $amount;

                if ($quantity <= self::EPSILON) {
                    $closedAt = $trade->transaction_date;
                }
            }

            if ($quantity > self::EPSILON) {
                $open[] = [
                    'isin' => $isin,
                    'name' => $name,
                    'quantity' => round($quantity, 8),
                    'invested' => round($costBasis, 2),
                    'average_price' => round($costBasis / $quantity, 4),
                    'opened_at' => $openedAt?->format('Y-m-d'),
                ];
            } elseif ($totalInvested > 0) {
                $closed[] = [
                    'isin' => $isin,
                    'name' => $name,
                    'invested' => round($totalInvested, 2),
                    'received' => round($totalReceived, 2),
                    'realized_gain' => round($totalReceived - $totalInvested, 2),
                    'opened_at' => $openedAt?->format('Y-m-d'),
                    'closed_at' => $closedAt?->format('Y-m-d'),
                ];
            }
        }

        return [
            'open' => collect($open)->sortByDesc('invested')->values()->all(),
            'closed' => collect($closed)->sortByDesc('closed_at')->values()->all(),
        ];
    }

    /**
     * "Buy trade" and "Savings plan execution" add to the position;
     * "Sell trade" removes from it. A leading "Cancellation" flips that
     * effect, since it reverses whichever of the two just happened.
     */
    private function increasesPosition(string $description): bool
    {
        $isCancellation = str_starts_with($description, 'Cancellation ');
        $isSell = str_contains($description, 'Sell trade');

        return $isSell ? $isCancellation : ! $isCancellation;
    }

    private function instrumentName(string $description): string
    {
        if (preg_match('/^(?:Cancellation\s+)?(?:Buy trade|Sell trade|Savings plan execution)\s+[A-Z]{2}[A-Z0-9]{10}\s+(.+?),\s*quantity:/', $description, $match)) {
            return $match[1];
        }

        return $description;
    }
}
