<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\PortfolioValueHistoryCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly PortfolioValueHistoryCalculator $historyCalculator,
        private readonly AccountBalanceCalculator $accountBalanceCalculator,
    ) {}

    /**
     * A quick snapshot on login: the user's cards at a glance, and - for
     * whichever cards are flagged as investment cards - the same portfolio
     * value chart and open positions shown in full on /investments, combined
     * across all of them (no per-card tabs here, just the overview).
     */
    public function index(Request $request): Response
    {
        $cards = Card::query()
            ->where('user_id', $request->user()->id)
            ->with('financialAccount')
            ->orderBy('name')
            ->get();

        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCards = $cards->where('is_investment_card', true);

        $investmentTransactions = Transaction::query()
            ->whereIn('card_id', $investmentCards->pluck('id'))
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        return Inertia::render('Dashboard', [
            'cards' => $cards,
            'positions' => $this->positionCalculator->calculate($investmentTransactions),
            'portfolioHistory' => $this->historyCalculator->calculate($investmentTransactions),
            'accountBalance' => $this->accountBalanceCalculator->totalFor($investmentCards),
        ]);
    }
}
