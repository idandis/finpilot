<?php

namespace Tests\Feature\Life;

use App\Models\Card;
use App\Models\CategoryBudget;
use App\Models\Exercise;
use App\Models\Meal;
use App\Models\Memory;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LifeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('life.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_index_defaults_to_the_current_year_with_the_right_week_count()
    {
        $user = User::factory()->create();
        $expectedWeeks = (int) Carbon::create(now()->year, 12, 28)->weekOfYear;

        $response = $this->actingAs($user)->get(route('life.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('year', now()->year)
            ->where('currentYear', now()->year)
            ->has('weeks', $expectedWeeks)
        );
    }

    public function test_a_specific_year_can_be_requested_via_query_param()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2020]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('year', 2020));
    }

    public function test_the_available_years_span_at_least_last_year_to_next_year()
    {
        $user = User::factory()->create();
        $currentYear = now()->year;

        $response = $this->actingAs($user)->get(route('life.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('availableYears.0', $currentYear - 1)
            ->where('availableYears', fn ($years) => collect($years)->contains($currentYear + 1))
        );
    }

    public function test_week_detail_requires_a_valid_week_number()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 60]));

        $response->assertNotFound();
    }

    public function test_week_detail_aggregates_finance_data()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => false]);

        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-03-03',
            'amount' => 1000,
            'direction' => 'income',
        ]);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-03-04',
            'amount' => 200,
            'direction' => 'expense',
        ]);
        // Outside the week (March 2-8, 2026) - must not be counted.
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-03-10',
            'amount' => 5000,
            'direction' => 'income',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('start', '2026-03-02')
            ->where('end', '2026-03-08')
            ->where('finance.income', 1000)
            ->where('finance.expenses', 200)
            ->where('finance.savings', 800)
        );
    }

    public function test_week_detail_excludes_investment_transactions_from_expenses()
    {
        $user = User::factory()->create();
        $investmentCard = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investmentCategory = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Investimenti']);

        Transaction::factory()->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investmentCategory->id,
            'transaction_date' => '2026-03-03',
            'amount' => 300,
            'direction' => 'expense',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('finance.invested', 300)
            ->where('finance.expenses', 0)
        );
    }

    public function test_week_detail_aggregates_health_and_organization_data()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $completeWorkout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-03-04']);
        $completeExercise = $completeWorkout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $completeExercise->sets()->create(['set_number' => 1, 'completed' => true]);

        $incompleteWorkout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-03-05']);
        $incompleteExercise = $incompleteWorkout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $incompleteExercise->sets()->create(['set_number' => 1, 'completed' => false]);

        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => '2026-03-03', 'meal_type' => 'lunch']);

        Task::factory()->create(['user_id' => $user->id, 'task_date' => '2026-03-03', 'status' => 'done']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => '2026-03-04', 'status' => 'todo']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('health.workouts_total', 2)
            ->where('health.workouts_completed', 1)
            ->where('health.meals_planned', 1)
            ->where('organization.tasks_total', 2)
            ->where('organization.tasks_completed', 1)
        );
    }

    public function test_week_detail_only_aggregates_the_authenticated_users_data()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => User::factory(), 'task_date' => '2026-03-03', 'status' => 'done']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('organization.tasks_total', 0));
    }

    public function test_week_detail_exposes_previous_and_next_week()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('previous.year', 2026)
            ->where('previous.week', 9)
            ->where('next.year', 2026)
            ->where('next.week', 11)
        );
    }

    public function test_week_detail_handles_the_year_boundary()
    {
        $user = User::factory()->create();
        $lastWeek = (int) Carbon::create(2025, 12, 28)->weekOfYear;

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 1]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('previous.year', 2025)
            ->where('previous.week', $lastWeek)
        );
    }

    public function test_week_detail_exposes_seven_days_with_their_memories()
    {
        $user = User::factory()->create();
        Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-03', 'title' => 'Cena tra amici', 'mood' => 'buono']);
        // Outside the week (March 2-8, 2026) - must not be attached to any day.
        Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-10', 'title' => 'Fuori settimana']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('days', 7)
            ->where('days.0.date', '2026-03-02')
            ->where('days.6.date', '2026-03-08')
            ->has('days.1.memories', 1)
            ->where('days.1.memories.0.title', 'Cena tra amici')
            ->where('days.1.memories.0.mood', 'buono')
            ->has('days.0.memories', 0)
        );
    }

    public function test_week_detail_only_shows_the_authenticated_users_memories()
    {
        $user = User::factory()->create();
        Memory::factory()->create(['user_id' => User::factory(), 'memory_date' => '2026-03-03', 'title' => 'Non mio']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('days.1.memories', 0));
    }

    public function test_week_detail_exposes_the_available_moods()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('moods.ottimo'));
    }

    public function test_primo_investimento_event_appears_on_its_date()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Investimenti']);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_category_id' => $category->id,
            'transaction_date' => '2026-03-04',
            'direction' => 'expense',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('days.2.events.0', 'Primo investimento'));
    }

    public function test_primo_investimento_event_does_not_repeat_on_a_later_week()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Investimenti']);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_category_id' => $category->id,
            'transaction_date' => '2026-02-01',
            'direction' => 'expense',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('days.2.events', 0));
    }

    public function test_nuova_carta_event_appears_when_a_card_is_created_in_the_week()
    {
        $user = User::factory()->create();
        Card::factory()->create(['user_id' => $user->id, 'name' => 'Carta Oro', 'created_at' => '2026-03-05']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('days.3.events.0', 'Nuova carta aggiunta: Carta Oro'));
    }

    public function test_primo_allenamento_event_appears_on_its_date()
    {
        $user = User::factory()->create();
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-03-06']);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('days.4.events.0', 'Primo allenamento'));
    }

    public function test_net_worth_threshold_event_fires_when_crossed_upward()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-03-05',
            'amount' => 6000,
            'direction' => 'income',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('days.3.events.0', 'Patrimonio ha superato 5.000 €'));
    }

    public function test_net_worth_threshold_event_does_not_fire_when_not_crossed()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-03-05',
            'amount' => 1000,
            'direction' => 'income',
        ]);

        $response = $this->actingAs($user)->get(route('life.week', ['year' => 2026, 'week' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('days.3.events', 0));
    }

    public function test_index_computes_the_productivity_metric_for_a_week()
    {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['user_id' => $user->id, 'task_date' => '2026-03-03', 'status' => 'done']);
        Task::factory()->count(2)->create(['user_id' => $user->id, 'task_date' => '2026-03-04', 'status' => 'todo']);

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weeks.9.metrics.productivity', 50));
    }

    public function test_index_computes_the_workouts_metric_for_a_week()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $complete = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-03-03']);
        $completeExercise = $complete->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $completeExercise->sets()->create(['set_number' => 1, 'completed' => true]);

        $incomplete = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-03-04']);
        $incompleteExercise = $incomplete->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $incompleteExercise->sets()->create(['set_number' => 1, 'completed' => false]);

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weeks.9.metrics.workouts', 50));
    }

    public function test_index_computes_the_mood_metric_for_a_week()
    {
        $user = User::factory()->create();
        Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-03', 'mood' => 'ottimo']);
        Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-04', 'mood' => 'pessimo']);

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weeks.9.metrics.mood', 60));
    }

    public function test_index_computes_the_budget_metric_for_a_week()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id]);
        CategoryBudget::factory()->create(['user_id' => $user->id, 'transaction_category_id' => $category->id, 'monthly_amount' => 100]);
        Transaction::factory()->create([
            'card_id' => $card->id,
            'transaction_category_id' => $category->id,
            'transaction_date' => '2026-03-03',
            'amount' => 50,
            'direction' => 'expense',
        ]);

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weeks.9.metrics.budget', 50));
    }

    public function test_index_metrics_are_null_when_there_is_no_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('life.index', ['anno' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('weeks.9.metrics.productivity', null)
            ->where('weeks.9.metrics.workouts', null)
            ->where('weeks.9.metrics.mood', null)
            ->where('weeks.9.metrics.budget', null)
        );
    }
}
