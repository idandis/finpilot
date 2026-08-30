<?php

namespace App\Models;

use Database\Factories\ShoppingListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A shopping list, which can be shared with other users of the platform:
 * everyone on it works on the same products, while the list itself
 * (renaming, deleting, inviting) stays with the owner. Same arrangement as
 * TaskBoard.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The people the owner invited - the owner themselves is not one of
     * them, see people().
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shopping_list_members')->withTimestamps();
    }

    /**
     * @return HasMany<ShoppingListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }

    /**
     * Everyone who shops from this list, owner first.
     *
     * @return Collection<int, User>
     */
    public function people(): Collection
    {
        return collect([$this->user])->concat($this->members)->values();
    }

    /**
     * Owner or invited member: the single check behind every list and
     * product action, since members are deliberately as powerful as the
     * owner on the list's contents (only the list itself is owner-only).
     */
    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }
}
