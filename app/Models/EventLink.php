<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event that exists both in Tribe and in a connected calendar. remote_version and local_hash are
 * what each side looked like after the last sync, so the next sync can tell which side changed.
 *
 * @property int $id
 * @property int $connection_id
 * @property int|null $event_id
 * @property string|null $remote_id
 * @property string|null $remote_version
 * @property string|null $local_hash
 * @property string $origin
 * @property-read Event|null $event
 * @property-read CalendarConnection $connection
 */
class EventLink extends Model
{
    public const FROM_TRIBE = 'tribe';

    public const FROM_REMOTE = 'remote';

    protected $fillable = ['connection_id', 'event_id', 'remote_id', 'remote_version', 'local_hash', 'origin'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<CalendarConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'connection_id');
    }
}
