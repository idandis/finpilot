<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InvestmentDecisionStoreRequest;
use App\Http\Requests\Finance\InvestmentDecisionUpdateRequest;
use App\Http\Requests\Finance\InvestmentLinkAnalysisRequest;
use App\Models\Investment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvestmentDecisionController extends Controller
{
    /**
     * Creates the Decision Journal record for an ISIN the user already
     * holds (reached from the Thesis tab's precompiled form) - only the
     * information that can't be recovered automatically: why they bought,
     * the thesis, what would change their mind, horizon and conviction.
     */
    public function store(InvestmentDecisionStoreRequest $request, string $isin): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->investments()->create([
            ...$data,
            'isin' => $isin,
            'current_confidence' => $data['initial_confidence'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Decision Journal created.')]);

        return to_route('investments.positions.show', $isin);
    }

    /**
     * Partial edits to the thesis, sell conditions, motivation or
     * conviction - the request only contains whichever slice the
     * submitting form sent, same pattern as CompanyAnalysisController.
     */
    public function update(InvestmentDecisionUpdateRequest $request, Investment $investment): RedirectResponse
    {
        $investment->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Investment updated.')]);

        return back();
    }

    /**
     * Attaches this Investment to a CompanyAnalysis for the Fundamentals
     * tab - either one the user already researched, or a brand new one
     * created on the spot from just a symbol and name (exactly like
     * CompanyAnalysisController::store), which the user can then refresh
     * from FMP right from the Fundamentals tab.
     */
    public function linkAnalysis(InvestmentLinkAnalysisRequest $request, Investment $investment): RedirectResponse
    {
        if ($request->filled('company_analysis_id')) {
            $analysis = $request->user()->companyAnalyses()
                ->findOrFail($request->validated('company_analysis_id'));
        } else {
            $analysis = $request->user()->companyAnalyses()->create([
                'name' => $request->validated('name'),
                'symbol' => strtoupper($request->validated('symbol')),
            ]);
        }

        $investment->update(['company_analysis_id' => $analysis->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fundamentals linked.')]);

        return back();
    }
}
