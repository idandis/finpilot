<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * I giri di soldi tra i propri conti.
 *
 * Non passano dalle categorie: un trasferimento non è un'entrata né un'uscita
 * del mese, sposta soltanto il saldo da una tasca all'altra. Un prelievo è un
 * giro come gli altri: dal conto al portafoglio contanti. Un lato può restare
 * vuoto per chi i contanti non li tiene tracciati.
 */
class AccountTransferController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        // Un giro di soldi è fra i propri conti: il budget può anche essere
        // condiviso, i conti no.
        $owner = $request->user();

        $sides = $this->sidesOf($owner, $validated);

        if ($sides === null) {
            return back()->withErrors([
                'to_financial_account_id' => 'Scegli due conti diversi tra cui spostare i soldi.',
            ]);
        }

        $owner->accountTransfers()->create($sides + [
            // Come per i movimenti: la firma è di chi l'ha scritto e non
            // cambia più, anche se poi lo corregge qualcun altro.
            'recorded_by_user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'transferred_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Trasferimento registrato');
    }

    public function update(Request $request, AccountTransfer $accountTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $accountTransfer);

        $validated = $this->validated($request);
        $sides = $this->sidesOf($accountTransfer->user, $validated);

        if ($sides === null) {
            return back()->withErrors([
                'to_financial_account_id' => 'Scegli due conti diversi tra cui spostare i soldi.',
            ]);
        }

        $accountTransfer->update($sides + [
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'transferred_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Trasferimento aggiornato');
    }

    public function destroy(Request $request, AccountTransfer $accountTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $accountTransfer);

        $accountTransfer->delete();

        return back()->with('success', 'Trasferimento eliminato');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        // `recorded_at` come per i movimenti: il pannello è lo stesso e un
        // solo nome di campo tiene insieme anche i messaggi di errore.
        return $request->validate([
            'from_financial_account_id' => 'nullable|integer|exists:financial_accounts,id',
            'to_financial_account_id' => 'nullable|integer|exists:financial_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'recorded_at' => 'required|date_format:Y-m-d H:i',
        ]);
    }

    /**
     * I due capi del trasferimento, o null se non ne esce uno spostamento
     * vero: un conto di qualcun altro vale come contanti, e due lati uguali
     * (contanti compresi) non muovono niente.
     *
     * "Qualcun altro" comprende chi condivide il budget: i conti sono suoi e
     * non si spostano soldi da quelli.
     *
     * @param  array<string, mixed>  $validated
     * @return array{from_financial_account_id: int|null, to_financial_account_id: int|null}|null
     */
    private function sidesOf(User $owner, array $validated): ?array
    {
        $from = $this->accountOf($owner, $validated['from_financial_account_id'] ?? null);
        $to = $this->accountOf($owner, $validated['to_financial_account_id'] ?? null);

        if ($from === $to) {
            return null;
        }

        return [
            'from_financial_account_id' => $from,
            'to_financial_account_id' => $to,
        ];
    }

    private function accountOf(User $owner, mixed $accountId): ?int
    {
        if (! is_numeric($accountId)) {
            return null;
        }

        return $owner->financialAccounts()->whereKey((int) $accountId)->exists()
            ? (int) $accountId
            : null;
    }

    /** Un trasferimento lo tocca solo chi possiede i conti che muove. */
    private function authorizeTransfer(Request $request, AccountTransfer $transfer): void
    {
        abort_unless($transfer->user_id === $request->user()->id, 403);
    }
}
