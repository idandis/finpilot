<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InvestmentReviewStoreRequest;
use App\Models\Investment;
use App\Models\InvestmentReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvestmentReviewController extends Controller
{
    /**
     * Logs a quarterly (or ad hoc) check-in: the decision taken, the new
     * conviction score and a short note - and moves the Investment's own
     * current_confidence forward to match, so the Thesis tab always shows
     * where the conviction stands today while the Reviews tab keeps the
     * full history of how it got there.
     */
    public function store(InvestmentReviewStoreRequest $request, Investment $investment): RedirectResponse
    {
        $data = $request->validated();

        $investment->reviews()->create([
            ...$data,
            'score_before' => $investment->current_confidence,
        ]);

        $investment->update(['current_confidence' => $data['score_after']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review saved.')]);

        return back();
    }

    public function destroy(Request $request, InvestmentReview $review): RedirectResponse
    {
        abort_unless($review->investment->user_id === $request->user()->id, 403);

        $review->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review deleted.')]);

        return back();
    }
}
