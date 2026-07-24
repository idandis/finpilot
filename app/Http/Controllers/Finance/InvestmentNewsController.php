<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\InstrumentNewsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvestmentNewsController extends Controller
{
    public function __construct(
        private readonly InstrumentNewsRepository $newsRepository,
        private readonly EodhdCallBudget $budget,
    ) {}

    /**
     * Manually load/refresh the news feed for one ISIN, from the position
     * page's News tab. Always forced (a click means "I want fresh news
     * now"), but the news endpoint costs far more than a price/history call
     * (see EodhdMarketPriceProvider::NEWS_CALL_COST) and shares the same
     * daily budget as the price refresh flow - so this can silently do
     * nothing if today's budget can't cover it, same as a price refresh
     * that runs out of calls mid-way.
     */
    public function refresh(Request $request, string $isin): RedirectResponse
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCardIds = $request->user()->cards()
            ->where('is_investment_card', true)
            ->pluck('id');

        $ownsPosition = Transaction::query()
            ->whereIn('card_id', $investmentCardIds)
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->where('isin', $isin)
            ->exists();

        abort_unless($ownsPosition, 404);

        $status = $this->newsRepository->statusFor($isin);

        if (! $status['resolvable']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Le notizie non sono ancora disponibili: il prezzo di questo strumento non è stato ancora risolto.',
            ]);

            return back();
        }

        $callsUsed = $this->newsRepository->refresh($isin, $this->budget->remaining(), force: true);

        Inertia::flash('toast', [
            'type' => $callsUsed > 0 ? 'success' : 'error',
            'message' => $callsUsed > 0
                ? 'Notizie aggiornate.'
                : 'Budget giornaliero EODHD insufficiente per caricare le notizie oggi. Riprova domani.',
        ]);

        return back();
    }
}
