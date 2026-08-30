<?php

use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingListItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('shopping-lists', [ShoppingListController::class, 'index'])->name('shopping-lists.index');
    Route::post('shopping-lists', [ShoppingListController::class, 'store'])->name('shopping-lists.store');
    Route::get('shopping-lists/{shoppingList}', [ShoppingListController::class, 'show'])->name('shopping-lists.show');
    Route::patch('shopping-lists/{shoppingList}', [ShoppingListController::class, 'update'])->name('shopping-lists.update');
    Route::delete('shopping-lists/{shoppingList}', [ShoppingListController::class, 'destroy'])->name('shopping-lists.destroy');

    Route::post('shopping-lists/{shoppingList}/members', [ShoppingListController::class, 'storeMember'])->name('shopping-lists.members.store');
    Route::delete('shopping-lists/{shoppingList}/members/{user}', [ShoppingListController::class, 'destroyMember'])->name('shopping-lists.members.destroy');

    Route::post('shopping-lists/{shoppingList}/items', [ShoppingListItemController::class, 'store'])->name('shopping-list-items.store');
    Route::patch('shopping-list-items/{shoppingListItem}/move', [ShoppingListItemController::class, 'move'])->name('shopping-list-items.move');
    Route::patch('shopping-list-items/{shoppingListItem}/toggle', [ShoppingListItemController::class, 'toggle'])->name('shopping-list-items.toggle');
    Route::delete('shopping-list-items/{shoppingListItem}', [ShoppingListItemController::class, 'destroy'])->name('shopping-list-items.destroy');
});
