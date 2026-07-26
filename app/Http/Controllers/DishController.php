<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meals\DishStoreRequest;
use App\Models\Dish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function store(DishStoreRequest $request): RedirectResponse
    {
        $request->user()->dishes()->create($request->validated());

        return back();
    }

    public function destroy(Request $request, Dish $dish): RedirectResponse
    {
        abort_unless($dish->user_id === $request->user()->id, 403);

        $dish->delete();

        return back();
    }
}
