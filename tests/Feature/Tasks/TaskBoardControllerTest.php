<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBoardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_a_board()
    {
        $response = $this->post(route('task-boards.store'), ['name' => 'Lavoro']);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('task_boards', 0);
    }

    public function test_a_board_is_created_and_the_page_switches_to_it()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('task-boards.store'), ['name' => 'Lavoro']);

        $board = TaskBoard::query()->where('user_id', $user->id)->sole();
        $response->assertRedirect(route('tasks.index', ['board' => $board->id]));
        $this->assertSame('Lavoro', $board->name);
    }

    public function test_boards_are_appended_after_the_existing_ones()
    {
        $user = User::factory()->create();
        TaskBoard::factory()->create(['user_id' => $user->id, 'position' => 3]);

        $this->actingAs($user)->post(route('task-boards.store'), ['name' => 'Seconda']);

        $this->assertSame(4, TaskBoard::query()->where('name', 'Seconda')->sole()->position);
    }

    public function test_a_board_requires_a_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('task-boards.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('task_boards', 0);
    }

    public function test_a_board_can_be_renamed()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id, 'name' => 'Lavoro']);

        $this->actingAs($user)->patch(route('task-boards.update', $board), ['name' => 'Progetti']);

        $this->assertSame('Progetti', $board->fresh()->name);
    }

    public function test_a_board_belonging_to_someone_else_cannot_be_renamed()
    {
        $board = TaskBoard::factory()->create(['name' => 'Altrui']);

        $response = $this->actingAs(User::factory()->create())->patch(route('task-boards.update', $board), ['name' => 'Mia']);

        $response->assertForbidden();
        $this->assertSame('Altrui', $board->fresh()->name);
    }

    public function test_deleting_a_board_deletes_its_tasks_too()
    {
        $user = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $user->id]);
        $boardTask = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => $board->id, 'task_date' => null]);
        $dailyTask = Task::factory()->create(['user_id' => $user->id, 'task_date' => today()]);

        $response = $this->actingAs($user)->delete(route('task-boards.destroy', $board));

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseMissing('tasks', ['id' => $boardTask->id]);
        $this->assertDatabaseHas('tasks', ['id' => $dailyTask->id]);
        $this->assertDatabaseCount('task_boards', 0);
    }

    public function test_a_board_belonging_to_someone_else_cannot_be_deleted()
    {
        $board = TaskBoard::factory()->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('task-boards.destroy', $board));

        $response->assertForbidden();
        $this->assertDatabaseCount('task_boards', 1);
    }

    public function test_a_board_can_be_shared_with_another_user_by_email()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $board = TaskBoard::factory()->create(['user_id' => $owner->id, 'name' => 'Lavoro']);

        $this->actingAs($owner)->post(route('task-boards.members.store', $board), ['email' => 'mate@example.com']);

        $this->assertTrue($board->members()->whereKey($mate->id)->exists());
    }

    public function test_a_shared_board_shows_up_in_the_other_users_board_strip()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id, 'name' => 'Lavoro']);
        $board->members()->attach($mate->id);

        $response = $this->actingAs($mate)->get(route('tasks.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('boards', 1)
            ->where('boards.0.name', 'Lavoro')
            ->where('boards.0.is_shared', true)
        );
    }

    public function test_an_unknown_email_cannot_be_invited()
    {
        $owner = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post(route('task-boards.members.store', $board), ['email' => 'nessuno@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, $board->members()->count());
    }

    public function test_the_owner_cannot_invite_themselves_or_an_existing_member_twice()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach($mate->id);

        $this->actingAs($owner)
            ->post(route('task-boards.members.store', $board), ['email' => 'owner@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->post(route('task-boards.members.store', $board), ['email' => 'mate@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $board->members()->count());
    }

    public function test_only_the_owner_can_invite_rename_or_delete_a_shared_board()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $board = TaskBoard::factory()->create(['user_id' => $owner->id, 'name' => 'Lavoro']);
        $board->members()->attach($mate->id);

        $this->actingAs($mate)
            ->post(route('task-boards.members.store', $board), ['email' => $stranger->email])
            ->assertForbidden();
        $this->actingAs($mate)
            ->patch(route('task-boards.update', $board), ['name' => 'Mia'])
            ->assertForbidden();
        $this->actingAs($mate)
            ->delete(route('task-boards.destroy', $board))
            ->assertForbidden();

        $this->assertSame('Lavoro', $board->fresh()->name);
        $this->assertSame(1, $board->members()->count());
    }

    public function test_removing_a_member_unassigns_their_tasks_on_that_board_only()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $otherBoard = TaskBoard::factory()->create(['user_id' => $mate->id]);
        $board->members()->attach($mate->id);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null, 'assigned_to_user_id' => $mate->id]);
        $elsewhere = Task::factory()->create(['user_id' => $mate->id, 'task_board_id' => $otherBoard->id, 'task_date' => null, 'assigned_to_user_id' => $mate->id]);

        $this->actingAs($owner)->delete(route('task-boards.members.destroy', [$board, $mate]));

        $this->assertSame(0, $board->members()->count());
        $this->assertNull($task->fresh()->assigned_to_user_id);
        $this->assertSame($mate->id, $elsewhere->fresh()->assigned_to_user_id);
    }

    public function test_a_member_can_leave_a_board_but_cannot_remove_another_member()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $other = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach([$mate->id, $other->id]);

        $this->actingAs($mate)
            ->delete(route('task-boards.members.destroy', [$board, $other]))
            ->assertForbidden();

        $response = $this->actingAs($mate)->delete(route('task-boards.members.destroy', [$board, $mate]));

        $response->assertRedirect(route('tasks.index'));
        $this->assertFalse($board->members()->whereKey($mate->id)->exists());
        $this->assertTrue($board->members()->whereKey($other->id)->exists());
    }

    public function test_the_owner_cannot_be_removed_from_their_own_board()
    {
        $owner = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete(route('task-boards.members.destroy', [$board, $owner]));

        $response->assertForbidden();
    }
}
