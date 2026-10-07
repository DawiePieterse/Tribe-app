<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a gathering's who-brings-what list; household_id is whoever said they'd bring it.
 *
 * @property int $id
 * @property int $event_id
 * @property string $title
 * @property int|null $household_id
 * @property-read Event $event
 * @property-read Household|null $household
 */
class GatheringItem extends Model
{
    protected $fillable = ['event_id', 'title', 'household_id'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
