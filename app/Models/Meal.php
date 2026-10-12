<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One meal on a household's week plan: a recipe, or just a few words ("Braai", "Uiteet").
 *
 * @property int $id
 * @property int $household_id
 * @property CarbonImmutable $date
 * @property string $slot
 * @property string|null $title
 * @property int|null $recipe_id
 * @property-read Recipe|null $recipe
 */
class Meal extends Model
{
    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    /** @var array<string, mixed> */
    protected $attributes = ['slot' => 'dinner'];

    protected $fillable = ['household_id', 'date', 'slot', 'title', 'recipe_id', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function label(): string
    {
        return $this->recipe->title ?? (string) $this->title;
    }
}
