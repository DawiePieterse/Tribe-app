<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Find a date": a few possible dates, and each household says which ones suit them. Always for the
 * whole family. Once a date is chosen it becomes a gathering (event_id) and the poll closes.
 *
 * @property int $id
 * @property string $title
 * @property string|null $notes
 * @property int|null $event_id
 * @property CarbonImmutable|null $closed_at
 * @property-read Member|null $creator
 */
class DatePoll extends Model
{
    protected $fillable = ['title', 'notes', 'created_by', 'event_id', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'immutable_datetime'];
    }

    /** @return HasMany<DatePollOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(DatePollOption::class)->orderBy('date')->orderBy('time');
    }

    /** @return BelongsTo<Member, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
