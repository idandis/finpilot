<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InvestmentEventStoreRequest;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvestmentEventController extends Controller
{
    /**
     * Records a factual company update (earnings, guidance, an acquisition...)
     * on the Events subsection - deliberately without any hold/sell judgment,
     * which belongs on an InvestmentReview instead.
     */
    public function store(InvestmentEventStoreRequest $request, Investment $investment): RedirectResponse
    {
        $investment->events()->create([
            'event_type' => $request->validated('event_type'),
            'title' => $request->validated('title'),
            'event_date' => $request->validated('event_date'),
            'metrics' => $request->parsedMetrics(),
            'summary' => $request->validated('summary'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event added.')]);

        return back();
    }

    public function destroy(Request $request, InvestmentEvent $event): RedirectResponse
    {
        abort_unless($event->investment->user_id === $request->user()->id, 403);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return back();
    }
}
