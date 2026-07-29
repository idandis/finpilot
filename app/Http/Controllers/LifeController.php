<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\SpendingSummaryCalculator;
use App\Services\Life\Moods;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LifeController extends Controller
{
    /**
     * "Patrimonio supera soglia" fires once for every multiple of this
     * amount the combined balance crosses upward within a week.
     */
    private const NET_WORTH_THRESHOLD_STEP = 5000;

    public function __construct(
        private readonly AccountBalanceCalculator $balanceCalculator,
        private readonly SpendingSummaryCalculator $spendingCalculator,
    ) {}

    /**
     * The year defaults to the current one but any other year within the
     * available range can be requested via ?anno= - each week carries a
     * set of 0-100 metric scores (or null where there's no data) so the
     * frontend can color the grid by whichever metric is selected, entirely
     * client-side, without a round trip per switch.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $currentYear = $today->year;
        $year = $this->resolveYear($request->query('anno'), $currentYear);
        $user = $request->user();

        return Inertia::render('Life/Index', [
            'year' => $year,
            'currentYear' => $currentYear,
            'availableYears' => $this->availableYears($user, $currentYear),
            'weeks' => $this->withMetrics($this->weeksFor($year, $today), $user),
        ]);
    }

    /**
     * Aggregates, read-only, what every other module already knows about
     * this one week - no data is written or duplicated here, this is purely
     * a summary view over Transaction/Task/Meal/Workout.
     */
    public function week(Request $request, int $year, int $week): Response
    {
        abort_unless($week >= 1 && $week <= $this->weeksInYear($year), 404);

        $weekStart = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);
        $user = $request->user();

        $previousMonday = $weekStart->copy()->subDays(7);
        $nextMonday = $weekStart->copy()->addDays(7);

        return Inertia::render('Life/Week', [
            'year' => $year,
            'week' => $week,
            'start' => $weekStart->toDateString(),
            'end' => $weekEnd->toDateString(),
            'weeksInYear' => $this->weeksInYear($year),
            'previous' => ['year' => (int) $previousMonday->format('o'), 'week' => (int) $previousMonday->format('W')],
            'next' => ['year' => (int) $nextMonday->format('o'), 'week' => (int) $nextMonday->format('W')],
            'finance' => $this->financeSummary($user, $weekStart, $weekEnd),
            'health' => $this->healthSummary($user, $weekStart, $weekEnd),
            'organization' => $this->organizationSummary($user, $weekStart, $weekEnd),
            'days' => $this->daysFor($user, $weekStart),
            'moods' => Moods::ALL,
        ]);
    }

    private function resolveYear(?string $requested, int $currentYear): int
    {
        if ($requested === null || ! ctype_digit($requested)) {
            return $currentYear;
        }

        return (int) $requested;
    }

    /**
     * The earliest year with any data at all (across every module this page
     * summarizes), so the year bar reaches back as far as there's something
     * to look at - always spanning at least [currentYear - 1, currentYear + 1]
     * even for a brand new account with no history yet.
     *
     * @return array<int, int>
     */
    private function availableYears(User $user, int $currentYear): array
    {
        $cardIds = $user->cards()->pluck('id');

        $earliestDates = collect([
            Transaction::query()->whereIn('card_id', $cardIds)->min('transaction_date'),
            $user->tasks()->min('task_date'),
            $user->meals()->min('meal_date'),
            $user->workouts()->min('workout_date'),
        ])->filter();

        $earliestYear = $earliestDates->isEmpty()
            ? $currentYear - 1
            : min($currentYear - 1, Carbon::parse($earliestDates->min())->year);

        return range($earliestYear, $currentYear + 1);
    }

    /**
     * The last ISO-8601 week of a year always contains December 28th - the
     * standard trick to get a year's total week count (52 or 53) without a
     * calendar library.
     */
    private function weeksInYear(int $year): int
    {
        return (int) Carbon::create($year, 12, 28)->weekOfYear;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weeksFor(int $year, Carbon $today): array
    {
        return collect(range(1, $this->weeksInYear($year)))
            ->map(function (int $week) use ($year, $today) {
                $start = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
                $end = $start->copy()->addDays(6);

                return [
                    'week' => $week,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'isCurrent' => $today->between($start, $end),
                ];
            })
            ->all();
    }

    /**
     * Attaches a 0-100 "metrics" score to each week (or null where there's
     * no data for it yet) - computed once for the whole year in a handful
     * of queries (one per source table, one per distinct month touched, for
     * the budget figure) rather than once per week, then bucketed in PHP by
     * ISO year-week key.
     *
     * @param  array<int, array<string, mixed>>  $weeks
     * @return array<int, array<string, mixed>>
     */
    private function withMetrics(array $weeks, User $user): array
    {
        if ($weeks === []) {
            return $weeks;
        }

        $rangeStart = $weeks[0]['start'];
        $rangeEnd = $weeks[array_key_last($weeks)]['end'];

        $tasksByWeek = $this->bucketByIsoWeek(
            $user->tasks()->whereDate('task_date', '>=', $rangeStart)->whereDate('task_date', '<=', $rangeEnd)->get(['task_date', 'status']),
            fn (Task $task) => $task->task_date,
        );

        $workoutsByWeek = $this->bucketByIsoWeek(
            $user->workouts()
                ->whereDate('workout_date', '>=', $rangeStart)
                ->whereDate('workout_date', '<=', $rangeEnd)
                ->with('exercises.sets')
                ->get(),
            fn (Workout $workout) => $workout->workout_date,
        );

        $memoriesByWeek = $this->bucketByIsoWeek(
            $user->memories()
                ->whereDate('memory_date', '>=', $rangeStart)
                ->whereDate('memory_date', '<=', $rangeEnd)
                ->whereNotNull('mood')
                ->get(['memory_date', 'mood']),
            fn (Memory $memory) => $memory->memory_date,
        );

        $budgetRatioByMonth = $this->budgetRatioByMonth($user, $rangeStart, $rangeEnd);

        return array_map(function (array $week) use ($tasksByWeek, $workoutsByWeek, $memoriesByWeek, $budgetRatioByMonth) {
            $key = $this->isoWeekKey(Carbon::parse($week['start']));

            $tasks = $tasksByWeek->get($key, collect());
            $workouts = $workoutsByWeek->get($key, collect());
            $memories = $memoriesByWeek->get($key, collect());

            $completedWorkouts = $workouts->filter(fn (Workout $workout) => $this->isWorkoutComplete($workout))->count();

            $week['metrics'] = [
                'productivity' => $tasks->isEmpty() ? null : (int) round($tasks->where('status', 'done')->count() / $tasks->count() * 100),
                'workouts' => $workouts->isEmpty() ? null : (int) round($completedWorkouts / $workouts->count() * 100),
                'mood' => $memories->isEmpty() ? null : (int) round($memories->map(fn (Memory $memory) => Moods::SCORES[$memory->mood] ?? 3)->avg() / 5 * 100),
                'budget' => $budgetRatioByMonth[Carbon::parse($week['start'])->format('Y-m')] ?? null,
            ];

            return $week;
        }, $weeks);
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  callable(mixed): Carbon  $dateResolver
     * @return Collection<string, Collection<int, mixed>>
     */
    private function bucketByIsoWeek(Collection $items, callable $dateResolver): Collection
    {
        return $items->groupBy(fn ($item) => $this->isoWeekKey(Carbon::parse($dateResolver($item))));
    }

    private function isoWeekKey(Carbon $date): string
    {
        return $date->format('o-\WW');
    }

    /**
     * Averages "rispetto del budget" over every category that actually has
     * a monthly budget set (0% = spent at or beyond it, 100% = nothing
     * spent yet), one figure per calendar month - every week overlapping
     * that month shows the same figure, since budgets in this app are
     * monthly, not weekly.
     *
     * @return array<string, int|null>
     */
    private function budgetRatioByMonth(User $user, string $rangeStart, string $rangeEnd): array
    {
        $cards = $user->cards()->get();

        $months = collect();
        $cursor = Carbon::parse($rangeStart)->startOfMonth();
        $end = Carbon::parse($rangeEnd)->startOfMonth();

        while ($cursor->lte($end)) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $months->mapWithKeys(function (string $month) use ($user, $cards) {
            $rows = collect($this->spendingCalculator->calculate($user, $cards, $month))
                ->filter(fn (array $row) => $row['budget'] !== null && $row['budget'] > 0);

            if ($rows->isEmpty()) {
                return [$month => null];
            }

            $spent = $rows->sum('spent');
            $budget = $rows->sum('budget');
            $ratio = max(0, min(1, 1 - $spent / $budget)) * 100;

            return [$month => (int) round($ratio)];
        })->all();
    }

    /**
     * A workout counts as complete when it has at least one exercise, and
     * every exercise has at least one series, all marked done - same rule
     * the Allenamenti board itself uses client-side.
     */
    private function isWorkoutComplete(Workout $workout): bool
    {
        if ($workout->exercises->isEmpty()) {
            return false;
        }

        return $workout->exercises->every(function (WorkoutExercise $exercise) {
            return $exercise->sets->isNotEmpty() && $exercise->sets->every(fn ($set) => $set->completed);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function financeSummary(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        $cards = $user->cards()->get();
        $cardIds = $cards->pluck('id');

        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCardIds = $cards->where('is_investment_card', true)->pluck('id');

        $weekTransactions = Transaction::query()
            ->whereIn('card_id', $cardIds)
            ->whereDate('transaction_date', '>=', $weekStart->toDateString())
            ->whereDate('transaction_date', '<=', $weekEnd->toDateString())
            ->get(['id', 'card_id', 'transaction_category_id', 'amount', 'direction']);

        $investmentTransactionIds = $weekTransactions
            ->whereIn('card_id', $investmentCardIds)
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->pluck('id');

        $invested = (float) $weekTransactions->whereIn('id', $investmentTransactionIds)->sum('amount');
        $income = (float) $weekTransactions->where('direction', 'income')->sum('amount');
        $expenses = (float) $weekTransactions
            ->where('direction', 'expense')
            ->whereNotIn('id', $investmentTransactionIds)
            ->sum('amount');

        $netWorth = $cards->isEmpty()
            ? null
            : ($this->balanceCalculator->historyAsOf($cards, collect([$weekEnd->toDateString()]))[$weekEnd->toDateString()] ?? null);

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'savings' => round($income - $expenses - $invested, 2),
            'invested' => round($invested, 2),
            'net_worth' => $netWorth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSummary(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        $workouts = $user->workouts()
            ->whereDate('workout_date', '>=', $weekStart->toDateString())
            ->whereDate('workout_date', '<=', $weekEnd->toDateString())
            ->with('exercises.sets')
            ->get();

        $completed = $workouts->filter(fn (Workout $workout) => $this->isWorkoutComplete($workout))->count();

        $mealsPlanned = $user->meals()
            ->whereDate('meal_date', '>=', $weekStart->toDateString())
            ->whereDate('meal_date', '<=', $weekEnd->toDateString())
            ->count();

        return [
            'workouts_total' => $workouts->count(),
            'workouts_completed' => $completed,
            'meals_planned' => $mealsPlanned,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationSummary(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        $tasks = $user->tasks()
            ->whereDate('task_date', '>=', $weekStart->toDateString())
            ->whereDate('task_date', '<=', $weekEnd->toDateString())
            ->get(['status']);

        return [
            'tasks_total' => $tasks->count(),
            'tasks_completed' => $tasks->where('status', 'done')->count(),
        ];
    }

    /**
     * One entry per day of the week, each carrying whichever manually
     * created "ricordi" fall on that date - grouped here (one query) rather
     * than filtered client-side per day, since a memory's date is the only
     * thing tying it to a specific day of the timeline - plus any
     * automatically detected milestones for that same day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function daysFor(User $user, Carbon $weekStart): array
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        $memories = $user->memories()
            ->whereDate('memory_date', '>=', $weekStart->toDateString())
            ->whereDate('memory_date', '<=', $weekEnd->toDateString())
            ->orderBy('created_at')
            ->get();

        $events = $this->automaticEventsFor($user, $weekStart, $weekEnd);

        return collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $memories, $events) {
                $date = $weekStart->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'memories' => $memories
                        ->filter(fn (Memory $memory) => $memory->memory_date->isSameDay($date))
                        ->map(fn (Memory $memory) => $this->serializeMemory($memory))
                        ->values(),
                    'events' => $events[$date->toDateString()] ?? [],
                ];
            })
            ->all();
    }

    /**
     * Milestones detected from data the user already has, not written
     * anywhere - a "first ever" event (investment, workout) is just this
     * week's earliest record matching last on file; the net worth
     * threshold walks the same historyAsOf() cursor used for the weekly
     * "Patrimonio" figure, one snapshot per day instead of one per week, so
     * the exact day a threshold was crossed can be pinpointed.
     *
     * @return array<string, array<int, string>>
     */
    private function automaticEventsFor(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        $events = [];

        $addEvent = function (Carbon $date, string $label) use (&$events) {
            $events[$date->toDateString()][] = $label;
        };

        $firstInvestmentDate = $this->firstInvestmentDate($user);

        if ($firstInvestmentDate && $firstInvestmentDate->between($weekStart, $weekEnd)) {
            $addEvent($firstInvestmentDate, 'Primo investimento');
        }

        $newCards = $user->cards()
            ->whereDate('created_at', '>=', $weekStart->toDateString())
            ->whereDate('created_at', '<=', $weekEnd->toDateString())
            ->get();

        foreach ($newCards as $card) {
            $addEvent(Carbon::parse($card->created_at)->startOfDay(), "Nuova carta aggiunta: {$card->name}");
        }

        $firstWorkoutDate = $user->workouts()->min('workout_date');

        if ($firstWorkoutDate && Carbon::parse($firstWorkoutDate)->between($weekStart, $weekEnd)) {
            $addEvent(Carbon::parse($firstWorkoutDate), 'Primo allenamento');
        }

        $this->netWorthThresholdEvents($user, $weekStart, $addEvent);

        return $events;
    }

    private function firstInvestmentDate(User $user): ?Carbon
    {
        $cards = $user->cards()->get();

        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $date = Transaction::query()
            ->whereIn('card_id', $cards->where('is_investment_card', true)->pluck('id'))
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->min('transaction_date');

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * @param  callable(Carbon, string): void  $addEvent
     */
    private function netWorthThresholdEvents(User $user, Carbon $weekStart, callable $addEvent): void
    {
        $cards = $user->cards()->get();

        if ($cards->isEmpty()) {
            return;
        }

        $dates = collect([$weekStart->copy()->subDay()])
            ->concat(collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset)))
            ->map(fn (Carbon $date) => $date->toDateString());

        $balances = $this->balanceCalculator->historyAsOf($cards, $dates);
        $ordered = $dates->map(fn (string $date) => $balances[$date] ?? 0.0)->values();

        for ($i = 1; $i < $ordered->count(); $i++) {
            $previousBucket = (int) floor($ordered[$i - 1] / self::NET_WORTH_THRESHOLD_STEP);
            $currentBucket = (int) floor($ordered[$i] / self::NET_WORTH_THRESHOLD_STEP);

            for ($bucket = $previousBucket + 1; $bucket <= $currentBucket; $bucket++) {
                $threshold = $bucket * self::NET_WORTH_THRESHOLD_STEP;

                if ($threshold > 0) {
                    $label = 'Patrimonio ha superato '.number_format($threshold, 0, ',', '.').' €';
                    $addEvent($weekStart->copy()->addDays($i - 1), $label);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMemory(Memory $memory): array
    {
        return [
            'id' => $memory->id,
            'title' => $memory->title,
            'description' => $memory->description,
            'location' => $memory->location,
            'people' => $memory->people,
            'mood' => $memory->mood,
            'photo_url' => $memory->photo_path ? Storage::disk('public')->url($memory->photo_path) : null,
        ];
    }
}
