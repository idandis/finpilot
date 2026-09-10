<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Budget\BudgetAccountBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * I conti e le carte da cui passano i movimenti del budget.
 *
 * Sono gli stessi conti della sezione Finanza (`financial_accounts`): un conto
 * si descrive una volta sola, poi lo si sceglie registrando entrate e uscite.
 * Anche i contanti sono un conto: cosi' il portafoglio ha un saldo, si scala
 * quando si paga in contanti e cresce quando si preleva da una carta.
 */
class BudgetAccountController extends Controller
{
    public const TYPES = ['checking', 'credit_card', 'debit_card', 'prepaid_card', 'cash'];

    public function __construct(private readonly BudgetAccountBalances $balances) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Budget/Accounts/Index', [
            'accounts' => $this->serializeAccounts($request->user()),
            'accountTypes' => self::TYPES,
            'icons' => FinancialAccount::ICONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $owner = $request->user();

        if ($this->nameIsTaken($owner, $validated['name'])) {
            return back()->withErrors(['name' => 'Esiste già un conto con questo nome.']);
        }

        $owner->financialAccounts()->create($validated + [
            'currency' => 'EUR',
            // In fondo: chi lo crea decide dopo dove metterlo, trascinandolo.
            'position' => (int) $owner->financialAccounts()->max('position') + 1,
        ]);

        return back()->with('success', 'Conto creato');
    }

    public function update(Request $request, FinancialAccount $budgetAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $budgetAccount);

        $validated = $this->validated($request);

        if ($this->nameIsTaken($budgetAccount->user, $validated['name'], $budgetAccount)) {
            return back()->withErrors(['name' => 'Esiste già un conto con questo nome.']);
        }

        $budgetAccount->update($validated);

        return back()->with('success', 'Conto aggiornato');
    }

    /**
     * L'ordine scelto trascinando le righe.
     *
     * Arriva la lista completa degli id nell'ordine nuovo: la posizione di un
     * conto è il suo posto nell'elenco, e riscriverle tutte costa meno che
     * ragionare su cosa si è spostato dove.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|distinct|exists:financial_accounts,id',
        ]);

        $accounts = FinancialAccount::query()
            ->whereIn('id', $validated['ids'])
            ->with('user')
            ->get();

        foreach ($accounts as $account) {
            $this->authorizeAccount($request, $account);
        }

        foreach ($validated['ids'] as $position => $id) {
            $accounts->firstWhere('id', $id)?->update(['position' => $position]);
        }

        return back();
    }

    /**
     * Il conto sparisce, i movimenti no: restano nel mese in cui sono stati
     * registrati, semplicemente senza più un conto di provenienza.
     */
    public function destroy(Request $request, FinancialAccount $budgetAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $budgetAccount);

        $budgetAccount->delete();

        return back()->with('success', 'Conto eliminato');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(self::TYPES)],
            'bank_name' => 'nullable|string|max:255',
            'holder_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:34',
            'initial_balance' => 'required|numeric|between:-999999999.99,999999999.99',
            'color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'icon' => ['nullable', Rule::in(FinancialAccount::ICONS)],
            'hidden_from_stats' => 'boolean',
            'excluded_from_stats' => 'boolean',
        ]);
    }

    /** Con due conti omonimi nell'elenco non si capisce più quale si sta scegliendo. */
    private function nameIsTaken(User $owner, string $name, ?FinancialAccount $except = null): bool
    {
        return $owner->financialAccounts()
            ->where('name', $name)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
    }

    /**
     * I conti non si condividono.
     *
     * Un budget condiviso mette in comune categorie e movimenti, non i conti:
     * chi lo riceve vede con che carta ha pagato l'altro - è scritto sul
     * movimento - ma i conti che tocca restano i suoi.
     */
    private function authorizeAccount(Request $request, FinancialAccount $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAccounts(User $owner): array
    {
        $accounts = $owner->financialAccounts()->orderBy('position')->orderBy('name')->get();
        $movements = $this->balances->movementsOf($accounts);

        return $accounts->map(function (FinancialAccount $account) use ($movements) {
            $movement = $movements[$account->id] ?? ['income' => 0.0, 'expense' => 0.0, 'count' => 0];

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'bank_name' => $account->bank_name,
                'holder_name' => $account->holder_name,
                'iban' => $account->iban,
                'initial_balance' => (float) $account->initial_balance,
                'color' => $account->color,
                'icon' => $account->icon,
                'hidden_from_stats' => $account->hidden_from_stats,
                'excluded_from_stats' => $account->excluded_from_stats,
                'movements_count' => $movement['count'],
                'balance' => $this->balances->balanceOf($account, $movement),
            ];
        })->all();
    }
}
