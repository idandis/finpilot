<?php

namespace App\Http\Controllers\BalanceSheet;

use App\Http\Controllers\BalanceSheet\Concerns\LoadsLedgerData;
use App\Http\Controllers\Controller;
use App\Models\BalanceSheetMonthClosure;
use App\Models\BalanceSheetProfile;
use App\Services\BalanceSheet\BalanceSheetCategorySuggestionProvider;
use App\Services\Budget\BudgetMonthlyTotals;
use App\Services\BalanceSheet\BalanceSheetTotalsCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "ledger" is a single-page view of the whole balance sheet: every
 * section (overview, income, expenses, assets, liabilities, time, goals) is
 * switched client-side via a side rail, with records added, edited and
 * deleted inline through dialogs instead of navigating to separate pages.
 */
class LedgerController extends Controller
{
    use LoadsLedgerData;

    public function __construct(
        private readonly BalanceSheetTotalsCalculator $calculator,
        private readonly BalanceSheetCategorySuggestionProvider $suggestions,
        private readonly BudgetMonthlyTotals $budgetTotals,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = BalanceSheetProfile::forUser($user);

        return Inertia::render('balance-sheet/Ledger/Index', [
            'profile' => $profile,
            'overview' => $this->calculator->overview($user, $profile),
            'closures' => BalanceSheetMonthClosure::query()
                ->where('user_id', $user->id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BalanceSheetMonthClosure $closure) => [
                    'id' => $closure->id,
                    'year' => $closure->year,
                    'month' => $closure->month,
                    'income_total' => (float) $closure->income_total,
                    'expense_total' => (float) $closure->expense_total,
                    'cash_flow' => (float) $closure->cash_flow,
                    'cash_balance_after' => (float) $closure->cash_balance_after,
                    'closed_at' => $closure->created_at?->toIso8601String(),
                ])
                ->values(),
            'budgetMonths' => $this->budgetTotals->perMonth($user),
            ...$this->ledgerData($user, $this->suggestions),
        ]);
    }
}
