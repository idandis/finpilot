<?php

namespace App\Http\Controllers\Finance;

use App\Contracts\FundamentalDataProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CompanyAnalysisStoreRequest;
use App\Http\Requests\Finance\CompanyAnalysisUpdateRequest;
use App\Models\CompanyAnalysis;
use App\Models\CompanyAnalysisPriceHistory;
use App\Services\Finance\BuffettQuestions;
use App\Services\Finance\CompanyAnalysisPresenter;
use App\Services\Finance\CompanyAnalysisPriceHistoryRepository;
use App\Services\Finance\FmpIndicatorMapper;
use App\Services\Finance\TechnicalIndicators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAnalysisController extends Controller
{
    public function __construct(
        private readonly FundamentalDataProvider $provider,
        private readonly CompanyAnalysisPriceHistoryRepository $priceHistoryRepository,
    ) {}

    /**
     * All companies the user has analyzed so far, most recently touched
     * first.
     */
    public function index(Request $request): Response
    {
        $analyses = $request->user()->companyAnalyses()
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (CompanyAnalysis $analysis) => $this->present($analysis));

        return Inertia::render('finance/CompanyAnalyses/Index', [
            'analyses' => $analyses,
        ]);
    }

    /**
     * Show the form for starting a new analysis: just a label and the
     * ticker symbol to fetch fundamentals for later.
     */
    public function create(): Response
    {
        return Inertia::render('finance/CompanyAnalyses/Create');
    }

    public function store(CompanyAnalysisStoreRequest $request): RedirectResponse
    {
        $analysis = $request->user()->companyAnalyses()->create([
            'name' => $request->validated('name'),
            'symbol' => strtoupper($request->validated('symbol')),
        ]);

        return to_route('company-analyses.show', $analysis);
    }

    /**
     * The analysis page: fundamentals, valuation and the Buffett checklist,
     * each its own tab, each editable and persisted independently.
     */
    public function show(Request $request, CompanyAnalysis $companyAnalysis): Response
    {
        abort_unless($companyAnalysis->user_id === $request->user()->id, 403);

        $candles = $this->priceHistoryCandles($companyAnalysis);

        return Inertia::render('finance/CompanyAnalyses/Show', [
            'analysis' => $this->present($companyAnalysis),
            'buffettQuestions' => $this->buffettAnswers($companyAnalysis),
            'priceHistory' => $candles,
            'technicalAnalysis' => TechnicalIndicators::analyze($candles),
        ]);
    }

    /**
     * Manual edits to either the indicators or the Buffett answers - the
     * request only contains whichever slice the submitting form sent.
     */
    public function update(CompanyAnalysisUpdateRequest $request, CompanyAnalysis $companyAnalysis): RedirectResponse
    {
        $companyAnalysis->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Analysis updated.')]);

        return back();
    }

    /**
     * Attempt to prefill the indicators from FMP's free fundamentals
     * endpoints. This is a convenience, never a requirement: a null
     * response (missing/invalid API key, symbol not covered) just leaves
     * the existing values untouched and manually editable.
     */
    public function refreshIndicators(Request $request, CompanyAnalysis $companyAnalysis): RedirectResponse
    {
        abort_unless($companyAnalysis->user_id === $request->user()->id, 403);

        $fundamentals = $this->provider->fetchFundamentals($companyAnalysis->symbol);

        if ($fundamentals === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Could not fetch fundamentals from FMP (missing API key, or symbol not found). You can still enter the values manually.'),
            ]);

            return back();
        }

        $mapped = FmpIndicatorMapper::map($fundamentals);

        // FMP's profile endpoint can still resolve a stray/unrelated ticker
        // (e.g. a private company's symbol matching some thinly-traded,
        // unrelated listing) even when it has no real fundamentals to
        // offer. If every indicator came back null, this wasn't a
        // meaningful match - treat it as a failure rather than silently
        // saving a possibly-wrong current_price with nothing else.
        $hasFundamentalData = collect($mapped)
            ->except(['current_price', 'indicators_currency'])
            ->filter(fn ($value) => $value !== null)
            ->isNotEmpty();

        if (! $hasFundamentalData) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('FMP has no fundamental data for this symbol - it may not be publicly traded, or the ticker may not match. Nothing was updated.'),
            ]);

            return back();
        }

        $companyAnalysis->fill($mapped);
        $companyAnalysis->indicators_fetched_at = now();
        $companyAnalysis->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Indicators updated from FMP.')]);

        return back();
    }

    /**
     * Refreshes the cached daily OHLC price history (chart tab) from EODHD.
     * Throttled to once every 24h by the repository - clicking again sooner
     * just leaves the existing chart in place, no error shown. A failed
     * call (missing API key, exhausted daily budget, provider error) shows
     * an error toast but never touches previously cached candles.
     */
    public function refreshPriceHistory(Request $request, CompanyAnalysis $companyAnalysis): RedirectResponse
    {
        abort_unless($companyAnalysis->user_id === $request->user()->id, 403);

        $success = $this->priceHistoryRepository->refresh($companyAnalysis, now()->subYear(), now());

        Inertia::flash('toast', $success
            ? ['type' => 'success', 'message' => __('Price chart updated.')]
            : ['type' => 'error', 'message' => __('Could not fetch the price history from EODHD (missing API key, exhausted daily budget, or symbol not found).')]);

        return back();
    }

    public function destroy(Request $request, CompanyAnalysis $companyAnalysis): RedirectResponse
    {
        abort_unless($companyAnalysis->user_id === $request->user()->id, 403);

        $companyAnalysis->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Analysis deleted.')]);

        return to_route('company-analyses.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CompanyAnalysis $analysis): array
    {
        return CompanyAnalysisPresenter::present($analysis);
    }

    /**
     * The cached daily candles for the chart tab, in the same
     * {time, open, high, low, close} shape CandlestickChart.vue already
     * expects on the Mercato page - rows without a full OHLC set (only a
     * close, e.g. imported from a source that lacked it) are skipped rather
     * than rendered as a flat candle.
     *
     * @return array<int, array{time: string, open: float, high: float, low: float, close: float}>
     */
    private function priceHistoryCandles(CompanyAnalysis $analysis): array
    {
        /** @var Collection<int, CompanyAnalysisPriceHistory> $history */
        $history = $this->priceHistoryRepository->historyFor($analysis->symbol);

        return $history
            ->filter(fn (CompanyAnalysisPriceHistory $row) => $row->open_price !== null && $row->high_price !== null && $row->low_price !== null)
            ->map(fn (CompanyAnalysisPriceHistory $row) => [
                'time' => $row->price_date->format('Y-m-d'),
                'open' => (float) $row->open_price,
                'high' => (float) $row->high_price,
                'low' => (float) $row->low_price,
                'close' => (float) $row->close_price,
            ])
            ->values()
            ->all();
    }

    /**
     * Merge the fixed 10-question list with whatever answers/notes are
     * already stored, so the frontend always renders all 10 even for a
     * brand new analysis with nothing answered yet.
     *
     * @return array<int, array{key: string, label: string, answer: bool|null, notes: string|null}>
     */
    private function buffettAnswers(CompanyAnalysis $companyAnalysis): array
    {
        $stored = collect($companyAnalysis->buffett_answers ?? [])->keyBy('key');

        return collect(BuffettQuestions::ALL)
            ->map(fn (array $question) => [
                'key' => $question['key'],
                'label' => $question['label'],
                'answer' => $stored->get($question['key'])['answer'] ?? null,
                'notes' => $stored->get($question['key'])['notes'] ?? null,
            ])
            ->all();
    }
}
