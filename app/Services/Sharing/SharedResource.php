<?php

namespace App\Services\Sharing;

use App\Models\ShoppingList;
use App\Models\TaskBoard;
use App\Models\User;
use App\Notifications\SharedResourceActivity;
use App\Notifications\SharedResourceInvitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The three things this app lets people share - a shopping list, a task
 * board, a meal plan - seen through the one lens the notifications need:
 * what it is called, where it lives, and who is on it.
 *
 * Call sites read as sentences:
 *
 *     SharedResource::forShoppingList($list)->announce($user, 'ha aggiunto "Mele"');
 *
 * announce() only ever writes to the *other* people on the resource, so a
 * list nobody shares (the common case) costs one in-memory check and no
 * notification at all.
 */
final class SharedResource
{
    /**
     * @param  Collection<int, User>  $people  Owner + members, the actor included.
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $label,
        public readonly string $name,
        public readonly string $url,
        public readonly Collection $people,
    ) {}

    public static function forShoppingList(ShoppingList $list): self
    {
        return new self(
            kind: 'shopping_list',
            label: 'Lista della spesa',
            name: $list->name,
            url: route('shopping-lists.show', $list->id, absolute: false),
            people: $list->people(),
        );
    }

    public static function forTaskBoard(TaskBoard $board): self
    {
        return new self(
            kind: 'task_board',
            label: 'Board',
            name: $board->name,
            url: route('tasks.index', ['board' => $board->id], absolute: false),
            people: $board->people(),
        );
    }

    /**
     * A meal plan has no table of its own: it is simply a user's meals, so
     * the owner identifies it (see User::mealPlanPeople()).
     */
    public static function forMealPlan(User $owner): self
    {
        return new self(
            kind: 'meal_plan',
            label: 'Pianificazione pasti',
            name: 'Pasti di '.$owner->name,
            url: route('meals.index', ['plan' => $owner->id], absolute: false),
            people: $owner->mealPlanPeople(),
        );
    }

    /**
     * Tells everyone else on the resource what the actor just did, e.g.
     * 'ha aggiunto "Mele"'. Nobody else on it means nothing to send.
     */
    public function announce(User $actor, string $action): void
    {
        $this->tell($this->people->reject(fn (User $person) => $person->id === $actor->id), $actor, $action);
    }

    /**
     * Same, but to a hand-picked audience - used when the wording differs
     * for one person ("ti ha assegnato...") and for everyone else.
     *
     * @param  Collection<int, User>|array<int, User>  $people
     */
    public function tell(Collection|array $people, User $actor, string $action): void
    {
        $recipients = Collection::wrap($people)->reject(fn (User $person) => $person->id === $actor->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SharedResourceActivity($actor, $this, $action));
    }

    /**
     * Welcomes somebody just invited onto the resource - the one
     * notification that also goes out by email.
     */
    public function invite(User $invited, User $inviter): void
    {
        if ($invited->id === $inviter->id) {
            return;
        }

        $invited->notify(new SharedResourceInvitation($inviter, $this));
    }

    /**
     * The shape both notifications store in their `data` column and the
     * sidebar bell renders.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'name' => $this->name,
            'url' => $this->url,
        ];
    }
}
