<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InvestmentNoteStoreRequest;
use App\Models\InvestmentNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentNoteController extends Controller
{
    /**
     * Add a note to an instrument's position page. Not tied to a specific
     * card or transaction - just the user and the ISIN - so it survives
     * re-imports and card changes.
     */
    public function store(InvestmentNoteStoreRequest $request, string $isin): RedirectResponse
    {
        $request->user()->investmentNotes()->create([
            'isin' => $isin,
            'body' => $request->validated('body'),
        ]);

        return back();
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, InvestmentNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->delete();

        return back();
    }
}
