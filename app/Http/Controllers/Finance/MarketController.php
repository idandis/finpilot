<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\InstrumentPriceHistory;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\InstrumentPriceHistoryRepository;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\TechnicalIndicators;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller
{
    public function __construct(
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly InstrumentPriceHistoryRepository $historyRepository,
    ) {}

    /**
     * A watchlist-style view of the user's own open positions: a daily
     * candlestick chart for whichever one is selected, and a list of all of
     * them with their current price and day-over-day change. Reuses the
     * exact same cached price/history data as /investments - no separate
     * data source, just a different lens on it.
     */
    public function index(Request $request): Response
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCardIds = $request->user()->cards()
            ->where('is_investment_card', true)
            ->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('card_id', $investmentCardIds)
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        $positions = $this->positionCalculator->calculate($transactions)['open'];

        $instruments = collect($positions)
            ->map(fn (array $position) => $this->buildInstrument($position))
            ->sortByDesc(fn (array $instrument) => $instrument['current_price'] ?? 0)
            ->values()
            ->all();

        return Inertia::render('finance/Market/Index', [
            'instruments' => $instruments,
        ]);
    }

    /**
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>
     */
    private function buildInstrument(array $position): array
    {
        $history = $this->historyRepository->historyFor($position['isin']);

        // Candlesticks stay in the instrument's own currency (never
        // converted through a fluctuating historical exchange rate) - that
        // shows the asset's real price action undistorted; the current
        // value shown elsewhere in EUR is unaffected.
        $candles = $history
            ->filter(fn ($row) => $row->open_price !== null && $row->high_price !== null && $row->low_price !== null)
            ->map(fn ($row) => [
                'time' => $row->price_date->format('Y-m-d'),
                'open' => (float) $row->open_price,
                'high' => (float) $row->high_price,
                'low' => (float) $row->low_price,
                'close' => (float) $row->close_price,
            ])
            ->values()
            ->all();

        [$dayChange, $dayChangePercent] = $this->dayChange($history, $position['current_price']);

        return [
            'isin' => $position['isin'],
            'name' => $position['name'],
            'is_crypto' => $position['is_crypto'],
            'current_price' => $position['current_price'],
            'current_price_original' => $position['current_price_original'],
            'price_currency' => $position['price_currency'],
            'day_change' => $dayChange,
            'day_change_percent' => $dayChangePercent,
            'candles' => $candles,
            'analysis' => TechnicalIndicators::analyze($candles),
        ];
    }

    /**
     * Day-over-day change from the last two available closes. The percent
     * is currency-invariant (computed from the instrument's own currency),
     * so no historical FX conversion is needed there; the absolute amount
     * is then applied to the already-EUR-converted current price so it's
     * directly comparable to the rest of the app.
     *
     * @param  Collection<int, InstrumentPriceHistory>  $history
     * @return array{0: float|null, 1: float|null}
     */
    private function dayChange(Collection $history, ?float $currentPriceEur): array
    {
        $closes = $history->pluck('close_price')->map(fn ($value) => (float) $value);

        if ($closes->count() < 2) {
            return [null, null];
        }

        $latest = $closes->last();
        $previous = $closes->slice(-2, 1)->first();

        if ($previous <= 0) {
            return [null, null];
        }

        $percent = round((($latest - $previous) / $previous) * 100, 2);
        $amount = $currentPriceEur !== null ? round($currentPriceEur * ($percent / 100), 2) : null;

        return [$amount, $percent];
    }
}
