<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('tasks.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_board_only_shows_todays_tasks()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Oggi']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'title' => 'Ieri']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->addDay(), 'title' => 'Domani']);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Oggi')
            ->where('date', today()->toDateString())
        );
    }

    public function test_a_past_day_can_be_browsed_via_the_date_query_param()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Oggi']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDays(3), 'title' => 'Tre giorni fa']);

        $response = $this->actingAs($user)->get(route('tasks.index', ['date' => today()->subDays(3)->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Tre giorni fa')
            ->where('date', today()->subDays(3)->toDateString())
            ->where('today', today()->toDateString())
        );
    }

    public function test_a_malformed_date_falls_back_to_today()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tasks.index', ['date' => 'not-a-date']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('date', today()->toDateString()));
    }

    public function test_a_future_day_can_be_browsed_via_the_date_query_param()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Oggi']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->addWeek(), 'title' => 'Tra una settimana']);

        $response = $this->actingAs($user)->get(route('tasks.index', ['date' => today()->addWeek()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Tra una settimana')
            ->where('date', today()->addWeek()->toDateString())
        );
    }

    public function test_a_user_only_sees_their_own_tasks()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);
        Task::factory()->create(['user_id' => User::factory(), 'task_date' => today()]);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('tasks', 1));
    }

    public function test_a_user_can_create_a_task_which_starts_in_the_todo_column()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Scrivere report',
            'description' => 'Entro le 18',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Scrivere report',
            'description' => 'Entro le 18',
            'status' => 'todo',
            'position' => 0,
        ]);
    }

    public function test_a_task_can_be_created_without_a_description()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Solo titolo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['title' => 'Solo titolo', 'description' => null]);
    }

    public function test_creating_a_task_requires_a_title()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_a_user_can_create_a_task_for_a_future_day()
    {
        $user = User::factory()->create();
        $futureDate = today()->addDays(3);

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Preparare la presentazione',
            'task_date' => $futureDate->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Preparare la presentazione',
            'task_date' => $futureDate->toDateString().' 00:00:00',
        ]);
    }

    public function test_creating_a_task_for_a_past_day_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Non valido',
            'task_date' => today()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('task_date');
        $this->assertDatabaseMissing('tasks', ['title' => 'Non valido']);
    }

    public function test_new_tasks_are_appended_to_the_end_of_the_todo_column()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 1]);

        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Terzo']);

        $this->assertDatabaseHas('tasks', ['title' => 'Terzo', 'position' => 2]);
    }

    public function test_a_user_can_move_their_own_task_to_another_column()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'in_progress', 'position' => 0]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_moving_a_task_appends_it_to_the_end_of_the_target_column()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 0]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 1]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done', 'position' => 2]);
    }

    public function test_moving_a_task_can_insert_it_in_the_middle_of_the_target_column()
    {
        $user = User::factory()->create();
        $first = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 0]);
        $second = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 1]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 1]);

        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'status' => 'done', 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done', 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'status' => 'done', 'position' => 2]);
    }

    public function test_a_task_can_be_reordered_within_its_own_column()
    {
        $user = User::factory()->create();
        $first = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);
        $second = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 1]);
        $third = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 2]);

        $this->actingAs($user)->patch(route('tasks.move', $first), ['status' => 'todo', 'position' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'position' => 2]);
    }

    public function test_moving_requires_a_valid_status()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'archived', 'position' => 0]);

        $response->assertSessionHasErrors('status');
    }

    public function test_moving_requires_a_position()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'todo']);

        $response->assertSessionHasErrors('position');
    }

    public function test_a_user_cannot_move_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
    }

    public function test_a_user_can_move_a_task_on_a_future_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->addDay(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'in_progress', 'position' => 0]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_a_user_cannot_move_a_task_from_a_past_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
    }

    public function test_a_user_can_delete_their_own_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->delete(route('tasks.destroy', $task));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today()]);

        $response = $this->actingAs($user)->delete(route('tasks.destroy', $task));

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_a_user_can_delete_a_task_on_a_future_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->addDay()]);

        $response = $this->actingAs($user)->delete(route('tasks.destroy', $task));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_a_task_from_a_past_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay()]);

        $response = $this->actingAs($user)->delete(route('tasks.destroy', $task));

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_yesterdays_task_status_is_left_untouched_and_does_not_appear_today()
    {
        $user = User::factory()->create();
        $yesterdaysTask = Task::factory()->create([
            'user_id' => $user->id,
            'task_date' => today()->subDay(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('tasks', 0));
        $this->assertDatabaseHas('tasks', ['id' => $yesterdaysTask->id, 'status' => 'in_progress']);
    }

    public function test_a_user_can_update_their_own_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Vecchio titolo']);

        $response = $this->actingAs($user)->patch(route('tasks.update', $task), [
            'title' => 'Nuovo titolo',
            'description' => 'Nuova descrizione',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Nuovo titolo', 'description' => 'Nuova descrizione']);
    }

    public function test_a_user_can_update_a_task_on_a_future_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->addDay(), 'title' => 'Vecchio']);

        $response = $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => 'Nuovo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Nuovo']);
    }

    public function test_a_user_cannot_update_a_task_from_a_past_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'title' => 'Vecchio']);

        $response = $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => 'Nuovo']);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Vecchio']);
    }

    public function test_updating_a_task_requires_a_title()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_a_user_cannot_update_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today(), 'title' => 'Non mio']);

        $response = $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => 'Rubato']);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Non mio']);
    }

    public function test_a_user_can_reschedule_a_task_from_a_past_day_to_the_next_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task), [
            'task_date' => today()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_date' => today()->toDateString().' 00:00:00']);
    }

    public function test_a_user_can_reschedule_a_task_to_an_arbitrary_future_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo']);
        $targetDate = today()->addWeek();

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task), [
            'task_date' => $targetDate->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_date' => $targetDate->toDateString().' 00:00:00']);
    }

    public function test_rescheduling_appends_to_the_end_of_the_target_days_column()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.reschedule', $task), [
            'task_date' => today()->toDateString(),
        ]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'position' => 1]);
    }

    public function test_rescheduling_requires_a_date()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task));

        $response->assertSessionHasErrors('task_date');
    }

    public function test_rescheduling_into_the_past_is_rejected()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task), [
            'task_date' => today()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('task_date');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_date' => today()->toDateString().' 00:00:00']);
    }

    public function test_a_user_cannot_reschedule_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today()->subDay()]);

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task));

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_date' => today()->subDay()->toDateString().' 00:00:00']);
    }

    public function test_schedule_assigns_a_calendar_time_slot()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'scheduled_time' => null]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->toDateString(),
            'scheduled_time' => '09:15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'scheduled_time' => '09:15:00']);
    }

    public function test_schedule_can_move_a_task_to_a_future_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->addDay()->toDateString(),
            'scheduled_time' => '11:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'task_date' => today()->addDay()->toDateString().' 00:00:00',
            'scheduled_time' => '11:00:00',
        ]);
    }

    public function test_schedule_rejects_a_task_from_a_past_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay()]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->toDateString(),
            'scheduled_time' => '09:00',
        ]);

        $response->assertForbidden();
    }

    public function test_schedule_rejects_moving_a_task_into_the_past()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->subDay()->toDateString(),
            'scheduled_time' => '09:00',
        ]);

        $response->assertSessionHasErrors('task_date');
    }

    public function test_schedule_is_forbidden_for_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->toDateString(),
            'scheduled_time' => '09:00',
        ]);

        $response->assertForbidden();
    }

    public function test_the_daily_board_never_shows_tasks_belonging_to_another_board()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Daily']);
        Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'title' => 'Di board']);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Daily')
            ->where('board', null)
            ->has('boards', 1)
        );
    }

    public function test_a_board_shows_only_its_own_tasks()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id, 'name' => 'Lavoro']);
        $otherBoard = TaskBoard::factory()->create(['user_id' => $user->id]);
        Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'title' => 'Di board']);
        Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $otherBoard->id, 'task_date' => null, 'title' => 'Altra board']);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Daily']);

        $response = $this->actingAs($user)->get(route('tasks.index', ['board' => $board->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Di board')
            ->where('board.name', 'Lavoro')
        );
    }

    public function test_an_unknown_or_foreign_board_falls_back_to_the_daily_one()
    {
        $user = User::factory()->create();
        $foreignBoard = TaskBoard::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'title' => 'Daily']);

        foreach (['nope', 999999, $foreignBoard->id] as $requested) {
            $response = $this->actingAs($user)->get(route('tasks.index', ['board' => $requested]));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('board', null)
                ->has('tasks', 1)
                ->where('tasks.0.title', 'Daily')
            );
        }
    }

    public function test_a_task_created_on_a_board_has_no_date_and_is_appended_to_that_board()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'position' => 4]);

        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Nuovo', 'task_board_id' => $board->id]);

        $task = Task::query()->where('title', 'Nuovo')->sole();
        $this->assertSame($board->id, $task->task_board_id);
        $this->assertNull($task->task_date);
        $this->assertSame(5, $task->position);
    }

    public function test_a_task_cannot_be_created_on_a_board_belonging_to_someone_else()
    {
        $user = User::factory()->create();
        $foreignBoard = TaskBoard::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Nuovo', 'task_board_id' => $foreignBoard->id]);

        $response->assertSessionHasErrors('task_board_id');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_positions_on_a_board_are_independent_from_the_daily_board()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 7]);

        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Primo della board', 'task_board_id' => $board->id]);

        $this->assertSame(0, Task::query()->where('title', 'Primo della board')->sole()->position);
    }

    public function test_a_board_task_can_be_edited_moved_and_deleted_without_any_day_rule()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => 'Rinominato', 'description' => null]);
        $this->assertSame('Rinominato', $task->fresh()->title);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);
        $this->assertSame('done', $task->fresh()->status);

        $this->actingAs($user)->delete(route('tasks.destroy', $task));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_moving_a_board_task_only_reindexes_its_own_board()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        $first = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'position' => 0]);
        $second = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'position' => 1]);
        $daily = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.move', $second), ['status' => 'todo', 'position' => 0]);

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(0, $daily->fresh()->position);
    }

    public function test_a_board_task_cannot_be_rescheduled_to_a_day()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null]);

        $response = $this->actingAs($user)->patch(route('tasks.reschedule', $task), ['task_date' => today()->addDay()->toDateString()]);

        $response->assertForbidden();
        $this->assertNull($task->fresh()->task_date);
    }

    public function test_a_board_task_cannot_be_dropped_onto_the_calendar()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null]);

        $response = $this->actingAs($user)->patch(route('tasks.schedule', $task), [
            'task_date' => today()->toDateString(),
            'scheduled_time' => '10:00',
        ]);

        $response->assertForbidden();
    }

    public function test_a_member_sees_and_can_work_on_every_task_of_a_shared_board()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id, 'name' => 'Lavoro']);
        $board->members()->attach($mate->id);
        $ownersTask = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null, 'title' => "Dell'owner", 'status' => 'todo', 'position' => 0]);

        $response = $this->actingAs($mate)->get(route('tasks.index', ['board' => $board->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.title', "Dell'owner")
            ->where('board.is_owner', false)
            ->has('board.people', 2)
        );

        $this->actingAs($mate)->patch(route('tasks.update', $ownersTask), ['title' => 'Modificato', 'description' => null]);
        $this->assertSame('Modificato', $ownersTask->fresh()->title);

        $this->actingAs($mate)->patch(route('tasks.move', $ownersTask), ['status' => 'in_progress', 'position' => 0]);
        $this->assertSame('in_progress', $ownersTask->fresh()->status);

        $this->actingAs($mate)->delete(route('tasks.destroy', $ownersTask));
        $this->assertDatabaseMissing('tasks', ['id' => $ownersTask->id]);
    }

    public function test_a_member_can_create_a_task_on_a_shared_board_appended_after_everyone_elses()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach($mate->id);
        Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'position' => 0]);

        $this->actingAs($mate)->post(route('tasks.store'), ['title' => 'Del membro', 'task_board_id' => $board->id]);

        $task = Task::query()->where('title', 'Del membro')->sole();
        $this->assertSame($mate->id, $task->user_id);
        $this->assertSame($board->id, $task->task_board_id);
        $this->assertSame(1, $task->position);
    }

    public function test_a_board_that_is_not_shared_with_the_user_is_out_of_reach()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null]);

        $this->actingAs($stranger)->get(route('tasks.index', ['board' => $board->id]))
            ->assertInertia(fn ($page) => $page->where('board', null)->has('tasks', 0));

        $this->actingAs($stranger)->post(route('tasks.store'), ['title' => 'Intruso', 'task_board_id' => $board->id])
            ->assertSessionHasErrors('task_board_id');
        $this->actingAs($stranger)->patch(route('tasks.update', $task), ['title' => 'Intruso', 'description' => null])
            ->assertForbidden();
        $this->actingAs($stranger)->delete(route('tasks.destroy', $task))
            ->assertForbidden();
    }

    public function test_a_task_can_be_assigned_to_anyone_on_its_board_and_unassigned()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['name' => 'Nicolas Picco']);
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach($mate->id);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null]);

        $this->actingAs($mate)->patch(route('tasks.assign', $task), ['assigned_to_user_id' => $mate->id]);
        $this->assertSame($mate->id, $task->fresh()->assigned_to_user_id);

        $this->actingAs($owner)->get(route('tasks.index', ['board' => $board->id]))
            ->assertInertia(fn ($page) => $page->where('tasks.0.assignee.name', 'Nicolas Picco'));

        $this->actingAs($owner)->patch(route('tasks.assign', $task), ['assigned_to_user_id' => null]);
        $this->assertNull($task->fresh()->assigned_to_user_id);
    }

    public function test_a_task_cannot_be_assigned_to_someone_outside_its_board()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null]);

        $response = $this->actingAs($owner)->patch(route('tasks.assign', $task), ['assigned_to_user_id' => $stranger->id]);

        $response->assertSessionHasErrors('assigned_to_user_id');
        $this->assertNull($task->fresh()->assigned_to_user_id);
    }

    public function test_a_task_created_with_an_assignee_keeps_it()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach($mate->id);

        $this->actingAs($owner)->post(route('tasks.store'), [
            'title' => 'Assegnato subito',
            'task_board_id' => $board->id,
            'assigned_to_user_id' => $mate->id,
        ]);

        $this->assertSame($mate->id, Task::query()->where('title', 'Assegnato subito')->sole()->assigned_to_user_id);
    }

    public function test_a_daily_task_cannot_be_assigned()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.assign', $task), ['assigned_to_user_id' => null]);

        $response->assertForbidden();
    }
}
