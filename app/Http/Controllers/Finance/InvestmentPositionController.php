<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\InvestmentNote;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\InstrumentNewsRepository;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\NewsHighlightExtractor;
use App\Services\Finance\PortfolioValueHistoryCalculator;
use App\Services\Finance\TradeDescription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentPositionController extends Controller
{
    public function __construct(
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly PortfolioValueHistoryCalculator $historyCalculator,
        private readonly InstrumentNewsRepository $newsRepository,
        private readonly NewsHighlightExtractor $highlightExtractor,
    ) {}

    /**
     * A single instrument's own detail page: reuses the same position
     * calculator and portfolio-history calculator as /investments, simply
     * scoped to this one ISIN's transactions - so "invested"/"market_value"
     * naturally become this instrument's own numbers instead of the whole
     * portfolio's.
     */
    public function show(Request $request, string $isin): Response
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
            ->where('isin', $isin)
            ->orderByDesc('transaction_date')
            ->get(['id', 'transaction_date', 'amount', 'direction', 'description', 'isin', 'quantity']);

        abort_if($transactions->isEmpty(), 404);

        $name = $transactions
            ->map(fn (Transaction $transaction) => TradeDescription::parseTrade($transaction->description)['name'])
            ->filter()
            ->first() ?? $isin;

        $notes = InvestmentNote::query()
            ->where('user_id', $request->user()->id)
            ->where('isin', $isin)
            ->orderByDesc('created_at')
            ->get(['id', 'body', 'created_at']);

        $newsStatus = $this->newsRepository->statusFor($isin);

        return Inertia::render('finance/Investments/Position', [
            'isin' => $isin,
            'instrumentName' => $name,
            'positions' => $this->positionCalculator->calculate($transactions),
            'portfolioHistory' => $this->historyCalculator->calculate($transactions),
            'transactions' => $transactions->values(),
            'notes' => $notes,
            'news' => [
                'articles' => $newsStatus['articles']->values(),
                'fetchedAt' => $newsStatus['fetched_at']?->toIso8601String(),
                'resolvable' => $newsStatus['resolvable'],
                'highlights' => $this->highlightExtractor->extract($newsStatus['articles']),
            ],
        ]);
    }
}
