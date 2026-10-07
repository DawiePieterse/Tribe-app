<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $colour
 */
class Household extends Model
{
    protected $fillable = ['name', 'colour'];

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
}
