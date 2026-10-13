<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $colour
 * @property int|null $people how many they cook for; null means everyone in the household
 * @property string|null $town where they live, for the weather
 * @property string|null $lat
 * @property string|null $lon
 */
class Household extends Model
{
    public const MAX_PEOPLE = 20;

    protected $fillable = ['name', 'colour', 'people', 'town', 'lat', 'lon'];

    /** Colours offered for a household: aloe green, protea pink, saffron, sea blue, clay, plum. */
    public const COLOURS = ['#2E7D5B', '#C2185B', '#C98A1B', '#1F6FA8', '#A0522D', '#6A3D9A'];

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->orderByRaw("kind = 'child'")->orderBy('name');
    }

    /** @return HasMany<Member, $this> */
    public function adults(): HasMany
    {
        return $this->hasMany(Member::class)->where('kind', Member::ADULT);
    }

    public function hasWeather(): bool
    {
        return $this->lat !== null && $this->lon !== null;
    }

    /** How many sit down to eat: the number set for the household, else everyone in it. */
    public function peopleEating(): int
    {
        return max(1, min(self::MAX_PEOPLE, $this->people ?? $this->members()->count()));
    }
}
