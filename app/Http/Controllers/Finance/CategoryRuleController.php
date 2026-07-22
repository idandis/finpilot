<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CategoryRuleUpdateRequest;
use App\Models\CategoryRule;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryRuleController extends Controller
{
    /**
     * Show the user's category rules, learned from manual corrections or
     * created automatically during CSV import.
     */
    public function index(Request $request): Response
    {
        $rules = CategoryRule::query()
            ->where('user_id', $request->user()->id)
            ->with('category')
            ->orderByDesc('times_applied')
            ->orderBy('pattern')
            ->get();

        $categories = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/CategoryRules/Index', [
            'rules' => $rules,
            'categories' => $categories,
        ]);
    }

    /**
     * Update a rule's pattern, category or active state.
     */
    public function update(CategoryRuleUpdateRequest $request, CategoryRule $rule): RedirectResponse
    {
        $rule->update($request->validated());

        return back();
    }

    /**
     * Delete a rule.
     */
    public function destroy(Request $request, CategoryRule $rule): RedirectResponse
    {
        abort_unless($rule->user_id === $request->user()->id, 403);

        $rule->delete();

        return back();
    }
}
