<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A calendar entry. Kinds: an ordinary event, a yearly anniversary, a school holiday or term, or a
 * gathering (always whole-family, with RSVPs per household and a who-brings-what list). An entry can
 * repeat (weekly, fortnightly, monthly, yearly) until a date. Entries with a feed_id come from a
 * calendar subscribed by link and are replaced on every sync, so they can't be edited here.
 *
 * @property int $id
 * @property int|null $household_id
 * @property int|null $member_id
 * @property string $kind
 * @property string $title
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $location
 * @property string|null $notes
 * @property string $repeats
 * @property CarbonImmutable|null $repeat_until
 * @property int|null $feed_id
 * @property string|null $external_uid
 * @property int|null $host_household_id
 * @property int|null $created_by
 * @property-read Household|null $household
 * @property-read Member|null $member
 * @property-read Household|null $hostHousehold
 * @property-read CalendarFeed|null $feed
 */
class Event extends Model
{
    use HouseholdScoped;

    public const EVENT = 'event';

    public const ANNIVERSARY = 'anniversary';

    public const SCHOOL = 'school';

    public const GATHERING = 'gathering';

    public const KINDS = [self::EVENT, self::GATHERING, self::ANNIVERSARY, self::SCHOOL];

    public const ONCE = 'none';

    public const WEEKLY = 'weekly';

    public const FORTNIGHTLY = 'fortnightly';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const REPEATS = [self::ONCE, self::WEEKLY, self::FORTNIGHTLY, self::MONTHLY, self::YEARLY];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => self::EVENT, 'repeats' => self::ONCE];

    protected $fillable = [
        'household_id', 'member_id', 'kind', 'title', 'starts_on', 'ends_on', 'start_time', 'end_time',
        'location', 'notes', 'repeats', 'repeat_until', 'host_household_id', 'created_by',
        'feed_id', 'external_uid',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'repeat_until' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function hostHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'host_household_id');
    }

    /** @return HasMany<GatheringResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(GatheringResponse::class);
    }

    /** @return HasMany<GatheringItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GatheringItem::class)->orderBy('id');
    }

    /** @return BelongsTo<CalendarFeed, $this> */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(CalendarFeed::class, 'feed_id');
    }

    public function repeats(): bool
    {
        return $this->repeats !== self::ONCE;
    }

    public function isFromFeed(): bool
    {
        return $this->feed_id !== null;
    }

    public function isGathering(): bool
    {
        return $this->kind === self::GATHERING;
    }

    public function colour(): string
    {
        return $this->member?->displayColour()
            ?? $this->feed->colour
            ?? $this->hostHousehold->colour
            ?? $this->household->colour
            ?? '#1F4E79';
    }

    /** "14:00" style, or null for an all-day entry. */
    public function timeLabel(): ?string
    {
        if ($this->start_time === null) {
            return null;
        }

        $label = substr($this->start_time, 0, 5);

        return $this->end_time === null ? $label : $label.'–'.substr($this->end_time, 0, 5);
    }
}
