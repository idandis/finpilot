<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Event;
use App\Models\FinancialAccount;
use App\Models\Meal;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_it_shows_todays_tasks_but_not_other_days()
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'Oggi', 'task_date' => now()->toDateString()]);
        Task::factory()->for($user)->create(['title' => 'Domani', 'task_date' => now()->addDay()->toDateString()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('todayTasks', 1)
            ->where('todayTasks.0.title', 'Oggi')
        );
    }

    public function test_it_shows_todays_meals_but_not_other_days()
    {
        $user = User::factory()->create();
        Meal::factory()->for($user)->create(['title' => 'Pasta', 'meal_date' => now()->toDateString(), 'meal_type' => 'lunch']);
        Meal::factory()->for($user)->create(['title' => 'Ieri', 'meal_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('todayMeals', 1)
            ->where('todayMeals.0.title', 'Pasta')
        );
    }

    public function test_it_shows_todays_workout_with_its_exercises()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->create(['workout_date' => now()->toDateString(), 'title' => 'Push day']);
        $exercise = WorkoutExercise::factory()->for($workout)->create(['sets_count' => 3, 'reps_count' => 10]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('todayWorkout.title', 'Push day')
            ->where('todayWorkout.exercises.0.exercise_name', $exercise->exercise->name)
            ->where('todayWorkout.exercises.0.sets_count', 3)
        );
    }

    public function test_it_reports_no_workout_when_none_is_planned_for_today()
    {
        $user = User::factory()->create();
        Workout::factory()->for($user)->create(['workout_date' => now()->addDay()->toDateString()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('todayWorkout', null));
    }

    public function test_it_shows_todays_events_but_not_other_days()
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['title' => 'Riunione', 'start_at' => now()->setTime(10, 0), 'end_at' => now()->setTime(11, 0)]);
        Event::factory()->for($user)->create(['title' => 'La prossima settimana', 'start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHour()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('todayEvents', 1)
            ->where('todayEvents.0.title', 'Riunione')
        );
    }

    public function test_it_only_includes_investment_data_for_investment_flagged_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $regularCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        // Belongs to a non-investment card - must not count towards the
        // dashboard's combined positions/portfolio history.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $regularCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('positions.open', 1)
            ->where('positions.open.0.isin', 'IE00BK5BQT80')
            ->where('positions.open.0.invested', 200)
        );
    }

    public function test_it_reports_the_account_balance_for_the_users_investment_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('accountBalance', 800));
    }

    public function test_it_reports_a_null_account_balance_when_there_are_no_investment_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('accountBalance', null));
    }

    public function test_it_reports_a_zero_based_balance_for_a_standalone_investment_card()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true, 'financial_account_id' => null]);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $card->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('accountBalance', 50));
    }
}
