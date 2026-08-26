<?php

namespace App\Http\Controllers\BalanceSheet;

use App\Http\Controllers\Controller;
use App\Models\BalanceSheetMonthClosure;
use App\Services\BalanceSheet\BalanceSheetMonthCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonthCloseController extends Controller
{
    public function __construct(
        private readonly BalanceSheetMonthCloseService $closeService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->closeService->close($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mese chiuso.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.index');
    }

    /** Annulla una chiusura: solo l'ultima, e solo se è di chi la chiede. */
    public function destroy(Request $request, BalanceSheetMonthClosure $closure): RedirectResponse
    {
        $user = $request->user();

        abort_unless($closure->user_id === $user->id, 403);

        $latestId = BalanceSheetMonthClosure::query()
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->value('id');

        if ($closure->id !== $latestId) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Si può annullare solo la chiusura più recente.'),
            ]);

            return back();
        }

        $this->closeService->reopen($user, $closure);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chiusura annullata.')]);

        return back();
    }
}
