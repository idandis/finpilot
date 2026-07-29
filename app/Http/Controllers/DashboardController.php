<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\Workout;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\PortfolioValueHistoryCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly PortfolioValueHistoryCalculator $historyCalculator,
        private readonly AccountBalanceCalculator $accountBalanceCalculator,
    ) {}

    /**
     * A "good morning" home base: what's on today across tasks, meals,
     * workouts and the calendar, plus - for whichever cards are flagged as
     * investment cards - a compact reference to the portfolio's value and
     * trend (full detail lives on /investments, this is just the pulse).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        $cards = Card::query()
            ->where('user_id', $user->id)
            ->with('financialAccount')
            ->orderBy('name')
            ->get();

        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCards = $cards->where('is_investment_card', true);

        $investmentTransactions = Transaction::query()
            ->whereIn('card_id', $investmentCards->pluck('id'))
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        $todayTasks = $user->tasks()
            ->whereDate('task_date', $today)
            ->orderBy('position')
            ->get(['id', 'title', 'status', 'position']);

        $todayMeals = $user->meals()
            ->whereDate('meal_date', $today)
            ->orderBy('position')
            ->get(['id', 'title', 'meal_type', 'position']);

        $todayWorkout = $user->workouts()
            ->whereDate('workout_date', $today)
            ->with('exercises.exercise')
            ->first();

        $todayEvents = $user->events()
            ->where('start_at', '<=', $today->copy()->endOfDay())
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNull('end_at')->where('start_at', '>=', $today->copy()->startOfDay()))
                ->orWhere(fn ($q) => $q->whereNotNull('end_at')->where('end_at', '>=', $today->copy()->startOfDay())))
            ->orderBy('start_at')
            ->get(['id', 'title', 'start_at', 'end_at', 'type', 'all_day']);

        return Inertia::render('Dashboard', [
            'positions' => $this->positionCalculator->calculate($investmentTransactions),
            'portfolioHistory' => $this->historyCalculator->calculate($investmentTransactions),
            'accountBalance' => $this->accountBalanceCalculator->totalFor($investmentCards),
            'today' => $today->toDateString(),
            'todayTasks' => $todayTasks,
            'todayMeals' => $todayMeals,
            'todayWorkout' => $todayWorkout ? $this->serializeWorkout($todayWorkout) : null,
            'todayEvents' => $todayEvents,
        ]);
    }

    /**
     * @return array{id: int, title: string|null, exercises: array<int, array{id: int, exercise_name: string, sets_count: int, reps_count: int}>}
     */
    private function serializeWorkout(Workout $workout): array
    {
        return [
            'id' => $workout->id,
            'title' => $workout->title,
            'exercises' => $workout->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'exercise_name' => $exercise->exercise->name,
                'sets_count' => $exercise->sets_count,
                'reps_count' => $exercise->reps_count,
            ])->all(),
        ];
    }
}
