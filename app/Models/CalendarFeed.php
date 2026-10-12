<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A calendar subscribed by link (an .ics or webcal:// address), such as a school's term dates. Its
 * events are copied into Tribe on every sync (`php artisan tribe:sync-calendars`).
 *
 * @property int $id
 * @property int|null $household_id
 * @property string $name
 * @property string $url
 * @property string|null $colour
 * @property CarbonImmutable|null $synced_at
 * @property string|null $last_error
 */
class CalendarFeed extends Model
{
    use HouseholdScoped;

    protected $fillable = ['household_id', 'name', 'url', 'colour', 'synced_at', 'last_error', 'created_by'];

    protected function casts(): array
    {
        return ['synced_at' => 'immutable_datetime'];
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'feed_id');
    }

    /** webcal:// is how calendar links are often shared; it is plain https underneath. */
    public function fetchUrl(): string
    {
        return (string) preg_replace('~^webcals?://~i', 'https://', trim($this->url));
    }
}
