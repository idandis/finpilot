<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\TransactionImportRequest;
use App\Models\Card;
use App\Services\Finance\TransactionCsvImporter;
use App\Services\Finance\TransactionPdfImporter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransactionImportController extends Controller
{
    /**
     * Import a statement into the given card's account, as CSV or as a
     * Trade Republic PDF depending on the uploaded file. Statements often
     * span more than one calendar month, so every valid row is imported;
     * the user then lands on the month of the most recent transaction found.
     */
    public function store(
        TransactionImportRequest $request,
        Card $card,
        TransactionCsvImporter $csvImporter,
        TransactionPdfImporter $pdfImporter,
    ): RedirectResponse {
        $file = $request->file('file');
        $importer = $file->getClientOriginalExtension() === 'pdf' ? $pdfImporter : $csvImporter;

        $result = $importer->import(
            $card->financialAccount,
            $file,
            $card->id,
        );

        if ($result['error']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result['error']]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$result['imported']} transazioni importate, {$result['duplicates']} duplicate, {$result['skipped']} scartate.",
        ]);

        return to_route('cards.show', array_filter([
            'card' => $card,
            'year' => $result['latest_year'],
            'month' => $result['latest_month'],
        ]));
    }
}
