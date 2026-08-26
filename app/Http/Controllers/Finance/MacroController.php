<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\EconomicRegimeClassifier;
use App\Services\Finance\MacroIndicatorRefreshResult;
use App\Services\Finance\MacroIndicatorRefreshService;
use App\Services\Finance\MacroIndicatorRepository;
use App\Services\Finance\MarketOverviewRefreshResult;
use App\Services\Finance\MarketOverviewRefreshService;
use App\Services\Finance\MarketOverviewRepository;
use App\Services\Finance\MarketSentimentClassifier;
use App\Services\Finance\RiskSentimentClassifier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MacroController extends Controller
{
    public function __construct(
        private readonly MacroIndicatorRepository $repository,
        private readonly MarketOverviewRepository $marketRepository,
        private readonly MacroIndicatorRefreshService $macroRefreshService,
        private readonly MarketOverviewRefreshService $marketRefreshService,
        private readonly EconomicRegimeClassifier $regimeClassifier,
        private readonly RiskSentimentClassifier $riskClassifier,
        private readonly MarketSentimentClassifier $sentimentClassifier,
    ) {}

    public function index(): Response
    {
        // Computed once each and reused (via categorized()'s optional
        // $flat param) both for the grouped dashboard sections and for the
        // rule-engine classifiers below, instead of re-querying/re-deriving
        // the same indicators twice.
        $macroIndicators = $this->repository->flat();
        $marketInstruments = $this->marketRepository->flat();

        // The "settori" category is split out of the generic category list
        // and handed to its own "Settori" tab (a flat instrument list, not
        // grouped - there's only ever the one category).
        $marketCategories = $this->marketRepository->categorized($marketInstruments);
        $sectorCategory = collect($marketCategories)->firstWhere('key', 'settori');

        return Inertia::render('finance/Macro/Index', [
            'categories' => $this->repository->categorized($macroIndicators),
            'markets' => collect($marketCategories)->reject(fn (array $category) => $category['key'] === 'settori')->values()->all(),
            'sectors' => $sectorCategory['instruments'] ?? [],
            'regime' => $this->regimeClassifier->classify($macroIndicators),
            'riskSentiment' => $this->riskClassifier->classify($macroIndicators, $marketInstruments),
            'sentiment' => $this->sentimentClassifier->classify($marketInstruments),
        ]);
    }

    /**
     * Manually trigger the same macro indicator refresh the daily scheduler
     * runs (see routes/console.php) - useful right after setting up
     * FRED_API_KEY or just to see today's release without waiting for 07:00.
     */
    public function refresh(): RedirectResponse
    {
        $result = $this->macroRefreshService->refresh();

        Inertia::flash('toast', [
            'type' => $result->failedIndicators === [] ? 'success' : 'info',
            'message' => $this->refreshToastMessage($result),
        ]);

        return back();
    }

    private function refreshToastMessage(MacroIndicatorRefreshResult $result): string
    {
        if ($result->indicatorsRefreshed === 0 && $result->failedIndicators === []) {
            return 'Nessun nuovo dato: gli indicatori erano già aggiornati.';
        }

        if ($result->failedIndicators !== []) {
            return "Aggiornati {$result->indicatorsRefreshed} indicatori. Falliti: ".implode(', ', $result->failedIndicators).'.';
        }

        return "Aggiornati {$result->indicatorsRefreshed} indicatori macro.";
    }

    /**
     * Manually trigger the same watchlist refresh the daily scheduler runs
     * (see routes/console.php) - useful right after raising the EODHD
     * budget or just to see today's close without waiting for 06:30.
     */
    public function refreshMarkets(): RedirectResponse
    {
        $result = $this->marketRefreshService->refresh();

        Inertia::flash('toast', [
            'type' => $result->failedInstruments === [] ? 'success' : 'info',
            'message' => $this->refreshMarketsToastMessage($result),
        ]);

        return back();
    }

    private function refreshMarketsToastMessage(MarketOverviewRefreshResult $result): string
    {
        if ($result->instrumentsRefreshed === 0 && $result->failedInstruments === []) {
            return 'Nessun nuovo dato: gli strumenti erano già aggiornati.';
        }

        if ($result->failedInstruments !== []) {
            return "Aggiornati {$result->instrumentsRefreshed} strumenti. Falliti: ".implode(', ', $result->failedInstruments).'.';
        }

        return "Aggiornati {$result->instrumentsRefreshed} strumenti di mercato.";
    }
}
