<?php

namespace App\Models;

use Database\Factories\PasswordGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'icon'])]
class PasswordGroup extends Model
{
    /** @use HasFactory<PasswordGroupFactory> */
    use HasFactory;

    public const ICONS = ['apps', 'banking', 'email', 'notes', 'social', 'shopping', 'work', 'wifi', 'other'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PasswordEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PasswordEntry::class);
    }
}
