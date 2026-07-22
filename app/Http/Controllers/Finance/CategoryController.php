<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CategoryStoreRequest;
use App\Http\Requests\Finance\CategoryUpdateRequest;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Show every category visible to the user: the shared system defaults
     * plus their own custom ones.
     */
    public function index(Request $request): Response
    {
        $categories = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new category, owned by the current user.
     */
    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        $request->user()->transactionCategories()->create($request->validated());

        return back();
    }

    /**
     * Update one of the user's own categories. System categories (shared,
     * user_id null) can't be edited - see CategoryUpdateRequest::authorize().
     */
    public function update(CategoryUpdateRequest $request, TransactionCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back();
    }

    /**
     * Delete one of the user's own categories.
     */
    public function destroy(Request $request, TransactionCategory $category): RedirectResponse
    {
        abort_unless($category->user_id === $request->user()->id, 403);

        $category->delete();

        return back();
    }
}
