<?php

namespace App\Http\Controllers\BalanceSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceSheet\AssetStoreRequest;
use App\Http\Requests\BalanceSheet\AssetUpdateRequest;
use App\Models\BalanceSheetEntry;
use App\Services\BalanceSheet\BalanceSheetAssetService;
use App\Services\BalanceSheet\BalanceSheetCategorySuggestionProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function __construct(
        private readonly BalanceSheetAssetService $assetService,
        private readonly BalanceSheetCategorySuggestionProvider $suggestions,
    ) {}

    public function index(Request $request): Response
    {
        $assets = BalanceSheetEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'asset')
            ->with(['linkedIncome', 'linkedLiability', 'linkedExpense', 'linkedTime'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return Inertia::render('balance-sheet/Assets/Index', [
            'assets' => $assets,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('balance-sheet/Assets/Create', [
            'categorySuggestions' => $this->suggestions->forType($request->user(), 'asset'),
        ]);
    }

    public function store(AssetStoreRequest $request): RedirectResponse
    {
        $asset = $this->assetService->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attività aggiunta.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.assets.edit', $asset);
    }

    public function edit(Request $request, BalanceSheetEntry $asset): Response
    {
        abort_unless($asset->user_id === $request->user()->id && $asset->type === 'asset', 404);

        $asset->load(['linkedIncome', 'linkedLiability', 'linkedExpense', 'linkedTime']);

        return Inertia::render('balance-sheet/Assets/Edit', [
            'asset' => $asset,
            'categorySuggestions' => $this->suggestions->forType($request->user(), 'asset'),
        ]);
    }

    public function update(AssetUpdateRequest $request, BalanceSheetEntry $asset): RedirectResponse
    {
        $this->assetService->update($asset, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attività aggiornata.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.assets.edit', $asset);
    }

    public function destroy(Request $request, BalanceSheetEntry $asset): RedirectResponse
    {
        abort_unless($asset->user_id === $request->user()->id && $asset->type === 'asset', 404);

        $this->assetService->delete($asset);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attività eliminata.')]);

        return $request->boolean('from_ledger')
            ? back()
            : to_route('balance-sheet.assets.index');
    }
}
