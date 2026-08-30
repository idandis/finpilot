<?php

namespace App\Models;

use Database\Factories\MealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id The plan this meal belongs to: a meal plan has no table of
 *                        its own, it is simply a user's meals (see User::mealPlanIsAccessibleBy()).
 * @property int|null $assigned_to_user_id Who cooks it, if anyone - one of the plan's people.
 * @property int|null $dish_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $meal_date
 * @property string $meal_type
 * @property string|null $category
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['assigned_to_user_id', 'title', 'description', 'meal_date', 'meal_type', 'category', 'dish_id', 'position'])]
class Meal extends Model
{
    /** @use HasFactory<MealFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const MEAL_TYPES = ['lunch', 'dinner'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * The preconfigured dish this meal was created from, if any - only set
     * when dragged in from the dish library, never for ad-hoc meals. Used to
     * resolve ingredients when generating a shopping list from the week.
     *
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
