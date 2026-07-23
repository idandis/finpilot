<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BudgetUpdateRequest;
use App\Models\Card;
use App\Models\CategoryBudget;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Show every category visible to the user with the monthly budget they
     * set for it, if any. This is the target only - comparing it against
     * actual spending is a separate, later step.
     */
    public function index(Request $request): Response
    {
        $categories = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        $budgets = CategoryBudget::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('transaction_category_id');

        $rows = $categories->map(function (TransactionCategory $category) use ($budgets) {
            $budget = $budgets->get($category->id);

            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'monthly_budget' => $budget ? (float) $budget->monthly_amount : null,
                'card_id' => $budget?->card_id,
            ];
        })->values();

        $cards = Card::query()
            ->whereRelation('financialAccount', 'user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('finance/Budgets/Index', [
            'budgets' => $rows,
            'totalBudget' => (float) $rows->sum('monthly_budget'),
            'cards' => $cards,
        ]);
    }

    /**
     * Set, update, or (when the amount is null) remove the user's monthly
     * budget for a category. The card can be changed independently of the
     * amount - each field is only touched when actually sent.
     */
    public function update(BudgetUpdateRequest $request, TransactionCategory $category): RedirectResponse
    {
        $existing = CategoryBudget::query()
            ->where('user_id', $request->user()->id)
            ->where('transaction_category_id', $category->id)
            ->first();

        $amount = $request->has('monthly_amount')
            ? $request->validated('monthly_amount')
            : $existing?->monthly_amount;

        if ($amount === null) {
            $existing?->delete();

            return back();
        }

        $cardId = $request->has('card_id')
            ? $request->validated('card_id')
            : $existing?->card_id;

        CategoryBudget::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'transaction_category_id' => $category->id],
            ['monthly_amount' => $amount, 'card_id' => $cardId],
        );

        return back();
    }
}
