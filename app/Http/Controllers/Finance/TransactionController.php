<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\TransactionUpdateRequest;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Services\Finance\TransactionCategorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Update a transaction's category or description. Manually assigning a
     * category teaches the categorizer, so the next transaction from the
     * same merchant is categorized automatically.
     */
    public function update(TransactionUpdateRequest $request, Transaction $transaction, TransactionCategorizer $categorizer): RedirectResponse
    {
        $transaction->update($request->validated());

        if ($request->filled('transaction_category_id')) {
            $categorizer->learnFromCorrection(
                $transaction,
                (int) $request->validated('transaction_category_id'),
                $request->user()->id,
            );
        }

        return back();
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->financialAccount->user_id === $request->user()->id, 403);

        $transaction->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction deleted.')]);

        return back();
    }

    /**
     * Delete every transaction belonging to an account - the clean way to
     * recover from a bad or duplicated import: wipe the account's
     * transactions and re-import the statement fresh.
     */
    public function destroyAllForAccount(Request $request, FinancialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $count = $account->transactions()->count();
        $account->transactions()->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$count} transazioni eliminate. Puoi reimportare l'estratto conto.",
        ]);

        return back();
    }
}
