<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
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

    public function test_a_future_date_falls_back_to_today()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tasks.index', ['date' => today()->addWeek()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('date', today()->toDateString()));
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

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'in_progress']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_moving_a_task_appends_it_to_the_end_of_the_target_column()
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 0]);
        Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'done', 'position' => 1]);
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today(), 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done', 'position' => 2]);
    }

    public function test_moving_requires_a_valid_status()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'archived']);

        $response->assertSessionHasErrors('status');
    }

    public function test_a_user_cannot_move_another_users_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => User::factory(), 'task_date' => today(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done']);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
    }

    public function test_a_user_cannot_move_a_task_from_a_past_day()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()->subDay(), 'status' => 'todo']);

        $response = $this->actingAs($user)->patch(route('tasks.move', $task), ['status' => 'done']);

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
}
