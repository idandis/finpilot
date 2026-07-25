<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shopping\ShoppingListStoreRequest;
use App\Http\Requests\Shopping\ShoppingListUpdateRequest;
use App\Models\ShoppingList;
use App\Services\Shopping\GroceryCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
    /**
     * Every list the user owns, each with its products - grouping by
     * category happens client-side from this flat per-list array, using
     * the same category order as GroceryCategories::ALL.
     */
    public function index(Request $request): Response
    {
        $lists = $request->user()->shoppingLists()
            ->orderBy('name')
            ->with(['items' => fn ($query) => $query->orderBy('position')])
            ->get(['id', 'user_id', 'name']);

        return Inertia::render('ShoppingLists/Index', [
            'lists' => $lists,
            'categories' => GroceryCategories::ALL,
        ]);
    }

    /**
     * A single list's own page: name, the inline add-product row, and its
     * products grouped by category client-side.
     */
    public function show(Request $request, ShoppingList $shoppingList): Response
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);

        $shoppingList->load(['items' => fn ($query) => $query->orderBy('position')]);

        return Inertia::render('ShoppingLists/Show', [
            'list' => $shoppingList,
            'categories' => GroceryCategories::ALL,
        ]);
    }

    public function store(ShoppingListStoreRequest $request): RedirectResponse
    {
        $request->user()->shoppingLists()->create($request->validated());

        return back();
    }

    public function update(ShoppingListUpdateRequest $request, ShoppingList $shoppingList): RedirectResponse
    {
        $shoppingList->update($request->validated());

        return back();
    }

    public function destroy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);

        $shoppingList->delete();

        return back();
    }
}
