<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AccountStoreRequest;
use App\Http\Requests\Finance\AccountUpdateRequest;
use App\Models\Card;
use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Show the user's financial accounts.
     */
    public function index(Request $request): Response
    {
        $accounts = $request->user()->financialAccounts()
            ->with('cards')
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show the form for creating a new financial account.
     */
    public function create(): Response
    {
        return Inertia::render('finance/Accounts/Create', [
            'accountTypes' => FinancialAccount::TYPES,
        ]);
    }

    /**
     * Store a newly created financial account.
     */
    public function store(AccountStoreRequest $request): RedirectResponse
    {
        $request->user()->financialAccounts()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('accounts.index');
    }

    /**
     * Show the form for editing a financial account.
     */
    public function edit(Request $request, FinancialAccount $account): Response
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        return Inertia::render('finance/Accounts/Edit', [
            'account' => $account->load('cards'),
            'accountTypes' => FinancialAccount::TYPES,
            'cardTypes' => Card::TYPES,
        ]);
    }

    /**
     * Update a financial account.
     */
    public function update(AccountUpdateRequest $request, FinancialAccount $account): RedirectResponse
    {
        $account->fill($request->validated());
        $account->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('accounts.edit', $account);
    }

    /**
     * Delete a financial account.
     */
    public function destroy(Request $request, FinancialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('accounts.index');
    }
}
