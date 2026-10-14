<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use App\Services\Food\Portions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ingredients and method are kept as plain text, one ingredient per line, so they are easy to type
 * on a phone and easy to send to a shopping list line by line.
 *
 * @property int $id
 * @property int|null $household_id
 * @property string $title
 * @property string|null $servings
 * @property string|null $time
 * @property string|null $ingredients
 * @property string|null $method
 * @property string|null $notes
 * @property string|null $source_url
 * @property-read bool|null $is_favourite  set by scopeWithFavourite()
 */
class Recipe extends Model
{
    use HouseholdScoped;

    protected $fillable = ['household_id', 'title', 'servings', 'time', 'ingredients', 'method', 'notes', 'source_url', 'created_by'];

    /** @return BelongsToMany<Member, $this> */
    public function favouredBy(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'recipe_favourites');
    }

    /**
     * Adds `is_favourite` for this member.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithFavourite(Builder $query, Member $member): Builder
    {
        return $query->withExists(['favouredBy as is_favourite' => fn ($q) => $q->whereKey($member->id)]);
    }

    public function isFavouriteOf(Member $member): bool
    {
        return $this->favouredBy()->whereKey($member->id)->exists();
    }

    /** @return list<string> */
    public function ingredientLines(): array
    {
        return self::lines($this->ingredients);
    }

    /** The number in "6 porsies" or "Vir 4", if there is one, so the quantities can be scaled. */
    public function servingsCount(): ?int
    {
        return preg_match('/\d+/', (string) $this->servings, $m) === 1 && (int) $m[0] > 0 ? (int) $m[0] : null;
    }

    /**
     * The ingredients for this many people (quantities scaled from the recipe's own serving count).
     *
     * @return list<string>
     */
    public function ingredientLinesFor(int $written, int $people): array
    {
        return array_map(fn (string $line) => Portions::scale($line, $written, $people), $this->ingredientLines());
    }

    /** @return list<string> */
    public function methodSteps(): array
    {
        return self::lines($this->method);
    }

    /** @return list<string> */
    public static function lines(?string $text): array
    {
        $lines = array_map(fn (string $line) => trim((string) preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line)), preg_split('/\R/u', (string) $text) ?: []);

        return array_values(array_filter($lines, fn (string $line) => $line !== ''));
    }
}
