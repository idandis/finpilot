<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meals\DishStoreRequest;
use App\Http\Requests\Meals\DishUpdateRequest;
use App\Models\Dish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function store(DishStoreRequest $request): RedirectResponse
    {
        $dish = $request->user()->dishes()->create($request->safe()->only(['name', 'description', 'category']));

        $this->syncIngredients($dish, $request->validated('ingredients', []));

        return back();
    }

    public function update(DishUpdateRequest $request, Dish $dish): RedirectResponse
    {
        $dish->update($request->safe()->only(['name', 'description', 'category']));

        $this->syncIngredients($dish, $request->validated('ingredients', []));

        return back();
    }

    public function destroy(Request $request, Dish $dish): RedirectResponse
    {
        abort_unless($dish->user_id === $request->user()->id, 403);

        $dish->delete();

        return back();
    }

    /**
     * Replaces the dish's entire ingredient list with the submitted rows -
     * simpler and safer than diffing against existing ids, since ingredient
     * rows have no identity of their own outside this form.
     *
     * @param  array<int, array{name?: string, category?: string}>  $ingredients
     */
    private function syncIngredients(Dish $dish, array $ingredients): void
    {
        $dish->ingredients()->delete();

        foreach ($ingredients as $ingredient) {
            if (blank($ingredient['name'] ?? null)) {
                continue;
            }

            $dish->ingredients()->create([
                'name' => $ingredient['name'],
                'category' => ($ingredient['category'] ?? null) ?: 'altro',
            ]);
        }
    }
}
