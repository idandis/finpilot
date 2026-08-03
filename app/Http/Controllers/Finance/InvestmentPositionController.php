<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentNote;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\CompanyAnalysisPresenter;
use App\Services\Finance\InstrumentNewsRepository;
use App\Services\Finance\InvestmentMotivations;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\JournalTimelineBuilder;
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
        private readonly JournalTimelineBuilder $journalTimelineBuilder,
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

        $investment = $request->user()->investments()
            ->with([
                'companyAnalysis',
                'reviews' => fn ($query) => $query->orderByDesc('review_date'),
                'reviews.investmentEvent:id,title',
                'journalEntries',
                'events' => fn ($query) => $query->orderByDesc('event_date'),
            ])
            ->where('isin', $isin)
            ->first();

        return Inertia::render('finance/Investments/Position', [
            'isin' => $isin,
            'instrumentName' => $name,
            'positions' => $this->positionCalculator->calculate($transactions),
            'portfolioHistory' => $this->historyCalculator->calculate($transactions),
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->transaction_date,
                    'amount' => $transaction->amount,
                    'direction' => $transaction->direction,
                    'description' => $transaction->description,
                    'isin' => $transaction->isin,
                    'quantity' => $transaction->quantity,
                    'unit_price' => $transaction->quantity && $transaction->quantity != 0
                        ? (string) ($transaction->amount / $transaction->quantity)
                        : null,
                ];
            })->values(),
            'notes' => $notes,
            'news' => [
                'articles' => $newsStatus['articles']->values(),
                'fetchedAt' => $newsStatus['fetched_at']?->toIso8601String(),
                'resolvable' => $newsStatus['resolvable'],
                'highlights' => $this->highlightExtractor->extract($newsStatus['articles']),
            ],
            'motivationOptions' => InvestmentMotivations::ALL,
            'investment' => $investment ? $this->presentInvestment($investment) : null,
            'fundamentals' => $investment?->companyAnalysis ? CompanyAnalysisPresenter::present($investment->companyAnalysis) : null,
            'journal' => $investment
                ? $this->journalTimelineBuilder->build($transactions, $investment->journalEntries, $investment->reviews)
                : [],
            'companyAnalyses' => $request->user()->companyAnalyses()->orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentInvestment(Investment $investment): array
    {
        return [
            'id' => $investment->id,
            'company_analysis_id' => $investment->company_analysis_id,
            'motivation_reasons' => $investment->motivation_reasons ?? [],
            'motivation_note' => $investment->motivation_note,
            'thesis' => $investment->thesis,
            'sell_conditions' => $investment->sell_conditions,
            'time_horizon' => $investment->time_horizon,
            'initial_confidence' => $investment->initial_confidence,
            'current_confidence' => $investment->current_confidence,
            'next_review_date' => $investment->next_review_date?->format('Y-m-d'),
            'next_review_note' => $investment->next_review_note,
            'created_at' => $investment->created_at?->toIso8601String(),
            'updated_at' => $investment->updated_at?->toIso8601String(),
            'reviews' => $investment->reviews->map(fn ($review) => [
                'id' => $review->id,
                'investment_event_id' => $review->investment_event_id,
                'investment_event_title' => $review->investmentEvent?->title,
                'review_date' => $review->review_date->format('Y-m-d'),
                'decision' => $review->decision,
                'thesis_still_valid' => $review->thesis_still_valid,
                'score_before' => $review->score_before,
                'score_after' => $review->score_after,
                'note' => $review->note,
            ])->values(),
            'events' => $investment->events->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'title' => $event->title,
                'event_date' => $event->event_date->format('Y-m-d'),
                'metrics' => $event->metrics ?? [],
                'summary' => $event->summary,
            ])->values(),
        ];
    }
}
