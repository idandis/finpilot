<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shopping\ShoppingListItemMoveRequest;
use App\Http\Requests\Shopping\ShoppingListItemStoreRequest;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShoppingListItemController extends Controller
{
    /**
     * A new product is always appended to the end of its chosen category
     * within the list.
     */
    public function store(ShoppingListItemStoreRequest $request, ShoppingList $shoppingList): RedirectResponse
    {
        $nextPosition = 1 + ($shoppingList->items()
            ->where('category', $request->validated('category'))
            ->max('position') ?? -1);

        $shoppingList->items()->create([
            ...$request->validated(),
            'position' => $nextPosition,
        ]);

        return back();
    }

    /**
     * Drag-and-drop between category groups: the product is always
     * appended to the end of the target category (no fine-grained
     * reordering within a category).
     */
    public function move(ShoppingListItemMoveRequest $request, ShoppingListItem $shoppingListItem): RedirectResponse
    {
        $category = $request->validated('category');

        $nextPosition = 1 + (ShoppingListItem::query()
            ->where('shopping_list_id', $shoppingListItem->shopping_list_id)
            ->where('category', $category)
            ->max('position') ?? -1);

        $shoppingListItem->update(['category' => $category, 'position' => $nextPosition]);

        return back();
    }

    /**
     * Marks a product as bought (or un-marks it) - purely a display toggle,
     * the product stays in its list and category either way.
     */
    public function toggle(Request $request, ShoppingListItem $shoppingListItem): RedirectResponse
    {
        abort_unless($shoppingListItem->list->user_id === $request->user()->id, 403);

        $shoppingListItem->update(['purchased' => ! $shoppingListItem->purchased]);

        return back();
    }

    public function destroy(Request $request, ShoppingListItem $shoppingListItem): RedirectResponse
    {
        abort_unless($shoppingListItem->list->user_id === $request->user()->id, 403);

        $shoppingListItem->delete();

        return back();
    }
}
