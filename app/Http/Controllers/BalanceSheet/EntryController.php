<?php

namespace App\Http\Controllers\BalanceSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceSheet\EntryStoreRequest;
use App\Http\Requests\BalanceSheet\EntryUpdateRequest;
use App\Models\BalanceSheetEntry;
use App\Services\BalanceSheet\BalanceSheetCategorySuggestionProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    public const TYPES = ['income', 'expense', 'liability', 'time', 'goal'];

    public const LINKABLE_TO_ASSET = ['income', 'expense', 'time'];

    public function __construct(
        private readonly BalanceSheetCategorySuggestionProvider $suggestions,
    ) {}

    public function index(Request $request, string $type): Response
    {
        $user = $request->user();

        return Inertia::render('balance-sheet/Entries/Index', [
            'type' => $type,
            'entries' => BalanceSheetEntry::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request, string $type): Response
    {
        return Inertia::render('balance-sheet/Entries/Create', [
            'type' => $type,
            'categorySuggestions' => $this->suggestions->forType($request->user(), $type),
            'liabilities' => $type === 'expense' ? $this->suggestions->options($request->user(), 'liability') : [],
            'assets' => in_array($type, self::LINKABLE_TO_ASSET, true) ? $this->suggestions->options($request->user(), 'asset') : [],
        ]);
    }

    public function store(EntryStoreRequest $request, string $type): RedirectResponse
    {
        $request->user()->balanceSheetEntries()->create([
            ...$request->validated(),
            'type' => $type,
            'active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voce aggiunta.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.entries.index', $type);
    }

    public function edit(Request $request, string $type, BalanceSheetEntry $entry): Response
    {
        abort_unless($entry->user_id === $request->user()->id && $entry->type === $type, 404);

        return Inertia::render('balance-sheet/Entries/Edit', [
            'type' => $type,
            'entry' => $entry,
            'categorySuggestions' => $this->suggestions->forType($request->user(), $type),
            'liabilities' => $type === 'expense' ? $this->suggestions->options($request->user(), 'liability') : [],
            'assets' => in_array($type, self::LINKABLE_TO_ASSET, true) ? $this->suggestions->options($request->user(), 'asset') : [],
        ]);
    }

    public function update(EntryUpdateRequest $request, string $type, BalanceSheetEntry $entry): RedirectResponse
    {
        $entry->fill($request->validated());
        $entry->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voce aggiornata.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.entries.index', $type);
    }

    public function destroy(Request $request, string $type, BalanceSheetEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id && $entry->type === $type, 404);

        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voce eliminata.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.entries.index', $type);
    }
}
