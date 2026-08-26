<?php

namespace App\Services\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\User;

class BalanceSheetCategorySuggestionProvider
{
    /**
     * Category suggestions for a given entry type. Time entries also suggest
     * the names of existing assets and incomes, since time is often spent on
     * (or freed by) something the user already tracks there.
     *
     * @return array<int, string>
     */
    public function forType(User $user, string $type): array
    {
        $categories = BalanceSheetEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        if ($type === 'time') {
            $categories = $categories->merge(
                BalanceSheetEntry::query()
                    ->where('user_id', $user->id)
                    ->whereIn('type', ['asset', 'income'])
                    ->where('active', true)
                    ->orderBy('name')
                    ->pluck('name')
            );
        }

        return $categories->unique()->values()->all();
    }

    /**
     * Active entries of a given type as {id, name} options, used to populate
     * "linked asset" / "linked liability" selects.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function options(User $user, string $type): array
    {
        return BalanceSheetEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BalanceSheetEntry $entry) => ['id' => $entry->id, 'name' => $entry->name])
            ->all();
    }
}
