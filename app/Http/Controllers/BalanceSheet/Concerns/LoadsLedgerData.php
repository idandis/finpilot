<?php

namespace App\Http\Controllers\BalanceSheet\Concerns;

use App\Models\BalanceSheetEntry;
use App\Models\User;
use App\Services\BalanceSheet\BalanceSheetCategorySuggestionProvider;
use Illuminate\Support\Collection;

/**
 * Shared by every controller that renders the ledger's section panel
 * (the full-page /balance-sheet/ledger view and the slide-over on the main
 * overview): every entry the user owns, plus the category/linking options
 * the inline add/edit dialogs need, all in one Inertia-friendly payload.
 */
trait LoadsLedgerData
{
    /**
     * @return array{entries: Collection<int, BalanceSheetEntry>, categorySuggestions: Collection<string, array<int, string>>, liabilityOptions: array<int, array{id: int, name: string}>, assetOptions: array<int, array{id: int, name: string}>}
     */
    private function ledgerData(User $user, BalanceSheetCategorySuggestionProvider $suggestions): array
    {
        return [
            'entries' => BalanceSheetEntry::query()
                ->where('user_id', $user->id)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'categorySuggestions' => collect(BalanceSheetEntry::TYPES)
                ->mapWithKeys(fn (string $type) => [$type => $suggestions->forType($user, $type)]),
            'liabilityOptions' => $suggestions->options($user, 'liability'),
            'assetOptions' => $suggestions->options($user, 'asset'),
        ];
    }
}
