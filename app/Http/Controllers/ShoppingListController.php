<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shopping\ShoppingListMemberStoreRequest;
use App\Http\Requests\Shopping\ShoppingListStoreRequest;
use App\Http\Requests\Shopping\ShoppingListUpdateRequest;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Sharing\SharedResource;
use App\Services\Shopping\GroceryCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
    /**
     * Every list the user can shop from - the ones they own first, then the
     * ones shared with them - each with its products. Grouping by category
     * happens client-side from this flat per-list array, using the same
     * category order as GroceryCategories::ALL.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $withRelations = [
            'items' => fn ($query) => $query->orderBy('position'),
            'user:id,name,email',
            'members:id,name,email',
        ];

        $lists = $user->shoppingLists()->orderBy('name')->with($withRelations)->get()
            ->concat($user->sharedShoppingLists()->orderBy('name')->with($withRelations)->get())
            ->map(fn (ShoppingList $list) => $this->serializeList($list, $user));

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
        abort_unless($shoppingList->isAccessibleBy($request->user()), 403);

        $shoppingList->load([
            'items' => fn ($query) => $query->orderBy('position'),
            'user:id,name,email',
            'members:id,name,email',
        ]);

        return Inertia::render('ShoppingLists/Show', [
            'list' => $this->serializeList($shoppingList, $request->user()),
            'categories' => GroceryCategories::ALL,
        ]);
    }

    /**
     * A list with its products and everyone shopping from it - the same
     * shape on the grid and on the list's own page, so both can show (and
     * manage) who it is shared with.
     *
     * @return array<string, mixed>
     */
    private function serializeList(ShoppingList $list, User $user): array
    {
        return [
            'id' => $list->id,
            'user_id' => $list->user_id,
            'name' => $list->name,
            'is_shared' => $list->user_id !== $user->id,
            'is_owner' => $list->user_id === $user->id,
            'items' => $list->items,
            'people' => $list->people()->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'is_owner' => $person->id === $list->user_id,
            ])->all(),
        ];
    }

    public function store(ShoppingListStoreRequest $request): RedirectResponse
    {
        $request->user()->shoppingLists()->create($request->validated());

        return back();
    }

    public function update(ShoppingListUpdateRequest $request, ShoppingList $shoppingList): RedirectResponse
    {
        $previousName = $shoppingList->name;

        $shoppingList->update($request->validated());

        if ($shoppingList->name !== $previousName) {
            SharedResource::forShoppingList($shoppingList)
                ->announce($request->user(), "ha rinominato «{$previousName}» in «{$shoppingList->name}»");
        }

        return back();
    }

    /**
     * Shares the list with another user of the platform, found by the email
     * they signed up with (an unknown email is a validation error, not an
     * invite: there is nobody to invite yet). From that moment the list
     * shows up among their own, with the same powers over its products.
     */
    public function storeMember(ShoppingListMemberStoreRequest $request, ShoppingList $shoppingList): RedirectResponse
    {
        $member = User::query()->where('email', $request->validated('email'))->sole();

        $shoppingList->members()->syncWithoutDetaching([$member->id]);

        $shoppingList->load('members');

        SharedResource::forShoppingList($shoppingList)->invite($member, $request->user());

        return back();
    }

    /**
     * Removes someone from the list - the owner removing a member, or a
     * member leaving on their own.
     */
    public function destroyMember(Request $request, ShoppingList $shoppingList, User $user): RedirectResponse
    {
        $isOwner = $shoppingList->user_id === $request->user()->id;
        $isLeaving = $user->id === $request->user()->id;

        abort_unless($isOwner || $isLeaving, 403);
        abort_if($user->id === $shoppingList->user_id, 403, 'Il proprietario non può essere rimosso dalla lista.');

        $shoppingList->members()->detach($user->id);

        return $isLeaving ? to_route('shopping-lists.index') : back();
    }

    /**
     * Deleting a list deletes its products with it (cascade), and is the
     * owner's call alone - a member who wants out leaves instead, see
     * destroyMember().
     */
    public function destroy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);

        // Read while the list is still there: once deleted there is nobody
        // left to tell.
        $resource = SharedResource::forShoppingList($shoppingList);

        $shoppingList->delete();

        $resource->announce($request->user(), "ha eliminato la lista «{$resource->name}»");

        return back();
    }
}
