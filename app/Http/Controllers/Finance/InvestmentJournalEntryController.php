<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InvestmentJournalEntryStoreRequest;
use App\Models\Investment;
use App\Models\InvestmentJournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvestmentJournalEntryController extends Controller
{
    /**
     * A manual annotation on the Journal timeline - either standalone or
     * pinned to a specific buy/sell transaction of this same ISIN.
     */
    public function store(InvestmentJournalEntryStoreRequest $request, Investment $investment): RedirectResponse
    {
        $investment->journalEntries()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry added.')]);

        return back();
    }

    public function destroy(Request $request, InvestmentJournalEntry $journalEntry): RedirectResponse
    {
        abort_unless($journalEntry->investment->user_id === $request->user()->id, 403);

        $journalEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry deleted.')]);

        return back();
    }
}
