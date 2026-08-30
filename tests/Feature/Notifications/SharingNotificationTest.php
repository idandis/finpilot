<?php

namespace Tests\Feature\Notifications;

use App\Models\Meal;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use App\Notifications\SharedResourceActivity;
use App\Notifications\SharedResourceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SharingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_someone_to_a_shopping_list_notifies_them_by_mail_and_in_app()
    {
        Notification::fake();

        $owner = User::factory()->create(['name' => 'Iana']);
        $invited = User::factory()->create(['email' => 'mate@example.com']);
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Spesa']);

        $this->actingAs($owner)->post(route('shopping-lists.members.store', $list), ['email' => 'mate@example.com']);

        Notification::assertSentTo($invited, SharedResourceInvitation::class, function ($notification, $channels) use ($invited) {
            $this->assertSame(['mail', 'database'], $channels);
            $this->assertStringContainsString('Spesa', $notification->toMail($invited)->subject);
            $this->assertSame('invitation', $notification->toArray($invited)['type']);

            return true;
        });
    }

    public function test_inviting_someone_to_a_task_board_or_a_meal_plan_notifies_them_too()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $invited = User::factory()->create(['email' => 'mate@example.com']);
        $board = TaskBoard::factory()->create(['user_id' => $owner->id, 'name' => 'Casa']);

        $this->actingAs($owner)->post(route('task-boards.members.store', $board), ['email' => 'mate@example.com']);
        $this->actingAs($owner)->post(route('meal-plan.members.store'), ['email' => 'mate@example.com']);

        Notification::assertSentToTimes($invited, SharedResourceInvitation::class, 2);
    }

    public function test_a_product_added_to_a_shared_list_is_announced_to_everyone_but_the_actor()
    {
        Notification::fake();

        $owner = User::factory()->create(['name' => 'Iana']);
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Spesa']);
        $list->members()->attach($mate->id);

        $this->actingAs($mate)->post(route('shopping-list-items.store', $list), ['name' => 'Mele', 'category' => 'frutta']);

        Notification::assertSentTo($owner, SharedResourceActivity::class, function ($notification) use ($owner, $mate) {
            $data = $notification->toArray($owner);
            $this->assertStringContainsString($mate->name, $data['message']);
            $this->assertStringContainsString('Mele', $data['message']);
            $this->assertSame('shopping_list', $data['resource']['kind']);

            return true;
        });
        Notification::assertNotSentTo($mate, SharedResourceActivity::class);
    }

    public function test_a_list_nobody_shares_announces_nothing()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('shopping-list-items.store', $list), ['name' => 'Mele', 'category' => 'frutta']);

        Notification::assertNothingSent();
    }

    public function test_taking_a_product_and_deleting_it_are_both_announced()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach($mate->id);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'name' => 'Mele', 'purchased' => false]);

        $this->actingAs($mate)->patch(route('shopping-list-items.toggle', $item));
        $this->actingAs($mate)->delete(route('shopping-list-items.destroy', $item));

        Notification::assertSentToTimes($owner, SharedResourceActivity::class, 2);
    }

    public function test_a_task_assigned_on_a_shared_board_reads_differently_for_the_assignee()
    {
        Notification::fake();

        $owner = User::factory()->create(['name' => 'Iana']);
        $mate = User::factory()->create(['name' => 'Nicolas Picco']);
        $other = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach([$mate->id, $other->id]);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null, 'title' => 'Bollette']);

        $this->actingAs($owner)->patch(route('tasks.assign', $task), ['assigned_to_user_id' => $mate->id]);

        Notification::assertSentTo($mate, SharedResourceActivity::class, function ($notification) use ($mate) {
            $this->assertStringContainsString('ti ha assegnato', $notification->toArray($mate)['message']);

            return true;
        });
        Notification::assertSentTo($other, SharedResourceActivity::class, function ($notification) use ($other) {
            $this->assertStringContainsString('a Nicolas Picco', $notification->toArray($other)['message']);

            return true;
        });
    }

    public function test_completing_a_task_is_announced_but_reordering_within_a_column_is_not()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $board = TaskBoard::factory()->create(['user_id' => $owner->id]);
        $board->members()->attach($mate->id);
        $task = Task::factory()->create(['user_id' => $owner->id, 'task_board_id' => $board->id, 'task_date' => null, 'status' => 'todo', 'title' => 'Bollette']);

        $this->actingAs($mate)->patch(route('tasks.move', $task), ['status' => 'todo', 'position' => 0]);
        Notification::assertNothingSent();

        $this->actingAs($mate)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);

        Notification::assertSentTo($owner, SharedResourceActivity::class, function ($notification) use ($owner) {
            $this->assertStringContainsString('ha completato', $notification->toArray($owner)['message']);

            return true;
        });
    }

    public function test_a_daily_task_never_announces_anything()
    {
        Notification::fake();

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'task_board_id' => null, 'task_date' => Carbon::today()]);

        $this->actingAs($user)->patch(route('tasks.update', $task), ['title' => 'Altro']);
        $this->actingAs($user)->delete(route('tasks.destroy', $task));

        Notification::assertNothingSent();
    }

    public function test_meals_added_edited_and_deleted_on_a_shared_plan_are_announced()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $owner->mealPlanMembers()->attach($mate->id);

        $this->actingAs($mate)->post(route('meals.store'), [
            'title' => 'Pasta al pesto',
            'meal_date' => Carbon::today()->toDateString(),
            'meal_type' => 'lunch',
            'plan_user_id' => $owner->id,
        ]);

        $meal = Meal::query()->sole();

        $this->actingAs($mate)->patch(route('meals.update', $meal), ['title' => 'Pasta al ragù']);
        $this->actingAs($mate)->delete(route('meals.destroy', $meal));

        Notification::assertSentToTimes($owner, SharedResourceActivity::class, 3);
        Notification::assertNotSentTo($mate, SharedResourceActivity::class);
    }

    public function test_notifications_ride_along_with_every_page_and_can_be_read_and_cleared()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Spesa']);
        $list->members()->attach($mate->id);

        $this->actingAs($mate)->post(route('shopping-list-items.store', $list), ['name' => 'Mele', 'category' => 'frutta']);

        $this->actingAs($owner)->get(route('shopping-lists.index'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread', 1)
                ->has('notifications.items', 1)
                ->where('notifications.items.0.read', false)
                ->where('notifications.items.0.resource.url', '/shopping-lists/'.$list->id)
            );

        $this->actingAs($owner)->patch(route('notifications.read-all'));
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        $this->actingAs($owner)->delete(route('notifications.destroy-all'));
        $this->assertSame(0, $owner->fresh()->notifications()->count());
    }

    public function test_one_notification_can_be_marked_read_on_its_own()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach($mate->id);

        $this->actingAs($mate)->post(route('shopping-list-items.store', $list), ['name' => 'Mele', 'category' => 'frutta']);
        $this->actingAs($mate)->post(route('shopping-list-items.store', $list), ['name' => 'Pane', 'category' => 'panetteria']);

        $notification = $owner->fresh()->notifications()->latest()->first();

        $this->actingAs($owner)->patch(route('notifications.read', $notification->id));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_a_guest_page_carries_no_notifications()
    {
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('notifications', null));
    }
}
