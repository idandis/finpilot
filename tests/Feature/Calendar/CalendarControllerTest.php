<?php

namespace Tests\Feature\Calendar;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('calendar.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_defaults_to_month_view_anchored_on_today()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('view', 'mese')
            ->where('date', today()->toDateString())
            ->where('today', today()->toDateString())
        );
    }

    public function test_a_malformed_date_falls_back_to_today()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index', ['data' => 'non-una-data']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('date', today()->toDateString()));
    }

    public function test_an_unknown_view_falls_back_to_month()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'anno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('view', 'mese'));
    }

    public function test_day_view_only_returns_events_on_that_day()
    {
        $user = User::factory()->create();
        Event::factory()->create([
            'user_id' => $user->id,
            'title' => 'Dentro il giorno',
            'start_at' => today()->setTime(10, 0),
            'end_at' => today()->setTime(11, 0),
        ]);
        Event::factory()->create([
            'user_id' => $user->id,
            'title' => 'Fuori dal giorno',
            'start_at' => today()->addDay()->setTime(10, 0),
            'end_at' => today()->addDay()->setTime(11, 0),
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('view', 'giorno')
            ->has('items', 1)
            ->where('items.0.title', 'Dentro il giorno')
        );
    }

    public function test_month_view_range_is_padded_to_full_weeks()
    {
        $user = User::factory()->create();

        // 2026-08-01 is a Saturday, so the month view's grid should reach
        // back into the last Monday of July - an event that day must show.
        Event::factory()->create([
            'user_id' => $user->id,
            'title' => 'Fine luglio, dentro la griglia',
            'start_at' => '2026-07-27 09:00:00',
            'end_at' => '2026-07-27 10:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'mese', 'data' => '2026-08-15']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rangeStart', '2026-07-27')
            ->has('items', 1)
        );
    }

    public function test_a_promemoria_within_range_is_included()
    {
        $user = User::factory()->create();
        Event::factory()->promemoria()->create([
            'user_id' => $user->id,
            'title' => 'Chiamare il dentista',
            'start_at' => today()->setTime(15, 0),
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->where('items.0.source', 'evento')
            ->where('items.0.type', 'promemoria')
            ->where('items.0.end_at', null)
        );
    }

    public function test_only_the_authenticated_users_events_are_returned()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Event::factory()->create(['user_id' => $other->id, 'start_at' => today()->setTime(9, 0), 'end_at' => today()->setTime(10, 0)]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('items', 0));
    }

    public function test_an_unscheduled_task_appears_as_an_all_day_item()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Rispondere alle email', 'task_date' => today(), 'scheduled_time' => null]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->where('items.0.source', 'task')
            ->where('items.0.scheduled', false)
            ->where('items.0.all_day', true)
            ->where('items.0.href', route('tasks.index', ['date' => today()->toDateString()]))
        );
    }

    public function test_a_scheduled_task_carries_its_time()
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Chiamata cliente',
            'task_date' => today(),
            'scheduled_time' => '14:30:00',
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->where('items.0.scheduled', true)
            ->where('items.0.all_day', false)
            ->where('items.0.start_at', today()->setTime(14, 30)->format('Y-m-d\TH:i:s'))
        );
    }

    public function test_a_scheduled_workout_carries_its_time_and_links_to_its_page()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'title' => 'Gambe',
            'workout_date' => today(),
            'scheduled_time' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->where('items.0.source', 'allenamento')
            ->where('items.0.scheduled', true)
            ->where('items.0.start_at', today()->setTime(18, 0)->format('Y-m-d\TH:i:s'))
            ->where('items.0.href', route('workouts.show', $workout))
        );
    }

    public function test_events_tasks_and_workouts_are_merged_together()
    {
        $user = User::factory()->create();
        Event::factory()->create(['user_id' => $user->id, 'start_at' => today()->setTime(9, 0), 'end_at' => today()->setTime(10, 0)]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => today()]);

        $response = $this->actingAs($user)->get(route('calendar.index', ['vista' => 'giorno']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('items', 3));
    }
}
