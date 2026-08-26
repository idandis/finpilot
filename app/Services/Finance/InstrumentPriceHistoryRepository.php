<?php

namespace App\Services\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Models\InstrumentPrice;
use App\Models\InstrumentPriceHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InstrumentPriceHistoryRepository
{
    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * One-time backfill of an ISIN's full daily price history, used only by
     * the background refresh command. No-op if already backfilled or if the
     * symbol isn't resolved yet. Costs at most one API call. Only marks
     * history_backfilled_at when the call actually succeeded (even with an
     * empty result, e.g. a young instrument outside the free-tier window) -
     * a failed call is left retryable on the next run.
     */
    public function backfill(InstrumentPrice $record, CarbonInterface $from, CarbonInterface $to): int
    {
        if ($record->history_backfilled_at !== null || $record->code === null || $record->exchange === null) {
            return 0;
        }

        $history = $this->provider->fetchHistory($record->code, $record->exchange, $from, $to);

        if ($history === null) {
            return 1;
        }

        // A plain ['isin' => ..., 'price_date' => 'Y-m-d'] match array doesn't
        // reliably find an existing row via updateOrCreate() here: SQLite
        // persists the `date`-cast column with a " 00:00:00" time suffix, so
        // a raw string match against just the date part silently misses it
        // and re-insert collides with the unique index instead. whereDate()
        // compares by calendar day regardless of that stored time component.
        $existingDates = InstrumentPriceHistory::query()
            ->where('isin', $record->isin)
            ->pluck('price_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->flip();

        foreach ($history as $point) {
            $dateKey = $point->date->format('Y-m-d');
            $attributes = [
                'close_price' => $point->price,
                'open_price' => $point->open,
                'high_price' => $point->high,
                'low_price' => $point->low,
            ];

            if ($existingDates->has($dateKey)) {
                InstrumentPriceHistory::query()
                    ->where('isin', $record->isin)
                    ->whereDate('price_date', $dateKey)
                    ->update($attributes);
            } else {
                InstrumentPriceHistory::query()->create(['isin' => $record->isin, 'price_date' => $dateKey, ...$attributes]);
            }
        }

        $record->update(['history_backfilled_at' => now()]);

        return 1;
    }

    /**
     * Appends (or corrects) a single day's close onto an ISIN's history,
     * using a price InstrumentPriceRepository::refresh() already fetched -
     * no extra API call. backfill() above only ever runs once per ISIN, so
     * without this the portfolio value chart would freeze at that one-time
     * snapshot forever; every subsequent close-price refresh (scheduled or
     * the manual "Aggiorna chiusura" button) now keeps it moving forward
     * too. Matches on calendar day the same way backfill() does, since a
     * forced re-fetch of today's close must update the existing row rather
     * than collide with the unique index.
     */
    public function upsertLatest(string $isin, FetchedPrice $price): void
    {
        $dateKey = $price->date->format('Y-m-d');

        $existing = InstrumentPriceHistory::query()
            ->where('isin', $isin)
            ->whereDate('price_date', $dateKey)
            ->first();

        if ($existing !== null) {
            $existing->update(['close_price' => $price->price]);

            return;
        }

        InstrumentPriceHistory::query()->create([
            'isin' => $isin,
            'price_date' => $dateKey,
            'close_price' => $price->price,
        ]);
    }

    /**
     * Full price history for an ISIN, ordered by date - fetched once and
     * reused in memory by the caller, rather than queried per snapshot date.
     *
     * @return Collection<int, InstrumentPriceHistory>
     */
    public function historyFor(string $isin): Collection
    {
        return InstrumentPriceHistory::query()
            ->where('isin', $isin)
            ->orderBy('price_date')
            ->get(['price_date', 'close_price', 'open_price', 'high_price', 'low_price']);
    }
}
