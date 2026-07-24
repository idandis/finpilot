<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\TransactionImportRequest;
use App\Models\Card;
use App\Services\Finance\TransactionCsvImporter;
use App\Services\Finance\TransactionPdfImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TransactionImportController extends Controller
{
    /**
     * Import a statement into the given card, as CSV or as a Trade Republic
     * PDF depending on the uploaded file. Statements often span more than
     * one calendar month, so every valid row is imported; the user then
     * lands on the month of the most recent transaction found.
     */
    public function store(
        TransactionImportRequest $request,
        Card $card,
        TransactionCsvImporter $csvImporter,
        TransactionPdfImporter $pdfImporter,
    ): RedirectResponse {
        $file = $request->file('file');
        $importer = $file->getClientOriginalExtension() === 'pdf' ? $pdfImporter : $csvImporter;

        $result = $importer->import($card, $file);

        if ($result['error']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result['error']]);

            return back();
        }

        $skippedRows = $result['skipped_rows'] ?? [];
        $message = "{$result['imported']} transazioni importate, {$result['duplicates']} duplicate, {$result['skipped']} scartate.";

        if ($skippedRows !== []) {
            // Full detail goes to the log for diagnosis; only a short hint
            // about the first mismatch is shown to the user, to keep the
            // toast readable even when many rows fail to reconcile.
            Log::warning('Righe non riconciliate durante import estratto conto PDF', [
                'card_id' => $card->id,
                'rows' => $skippedRows,
            ]);

            $first = $skippedRows[0];
            $message .= " Prima riga scartata: {$first['date']} {$first['tipo']} \"{$first['description']}\".";
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route('cards.show', array_filter([
            'card' => $card,
            'year' => $result['latest_year'],
            'month' => $result['latest_month'],
        ]));
    }
}
