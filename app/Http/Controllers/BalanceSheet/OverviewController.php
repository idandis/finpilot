<?php

namespace App\Http\Controllers\BalanceSheet;

use App\Http\Controllers\BalanceSheet\Concerns\LoadsLedgerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceSheet\ProfileUpdateRequest;
use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetMonthClosure;
use App\Models\BalanceSheetProfile;
use App\Services\BalanceSheet\BalanceSheetCategorySuggestionProvider;
use App\Services\BalanceSheet\BalanceSheetTotalsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    use LoadsLedgerData;

    public function __construct(
        private readonly BalanceSheetTotalsCalculator $calculator,
        private readonly BalanceSheetCategorySuggestionProvider $suggestions,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = BalanceSheetProfile::forUser($user);

        return Inertia::render('balance-sheet/Overview/Index', [
            'overview' => $this->calculator->overview($user, $profile),
            'profile' => $profile,
            'goals' => BalanceSheetEntry::query()
                ->where('user_id', $user->id)
                ->where('type', 'goal')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'recentClosures' => BalanceSheetMonthClosure::query()
                ->where('user_id', $user->id)
                ->latest()
                ->take(6)
                ->get(),
            ...$this->ledgerData($user, $this->suggestions),
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $profile = BalanceSheetProfile::forUser($request->user());
        $profile->fill($request->validated());
        $profile->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profilo aggiornato.')]);

        return to_route('balance-sheet.index');
    }
}
