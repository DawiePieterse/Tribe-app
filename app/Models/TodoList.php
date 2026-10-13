<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopping list or a task list ("List" is a reserved word in PHP, hence the name).
 *
 * @property int $id
 * @property int|null $household_id
 * @property string $name
 * @property string $kind
 * @property-read Household|null $household
 */
class TodoList extends Model
{
    use HouseholdScoped;

    public const SHOPPING = 'shopping';

    public const TASKS = 'tasks';

    protected $table = 'lists';

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => self::SHOPPING];

    protected $fillable = ['household_id', 'name', 'kind', 'created_by'];

    /** @return HasMany<ListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    /**
     * The shopping lists a member can add to, their own household's first.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeShoppingFor(Builder $query, Member $member): Builder
    {
        return $query->visibleTo($member)->where('kind', self::SHOPPING)->orderByRaw('household_id IS NULL')->orderBy('name');
    }

    public function isShopping(): bool
    {
        return $this->kind === self::SHOPPING;
    }
}
