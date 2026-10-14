<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int|null $there_member_id who usually takes a child there
 * @property int|null $back_member_id who usually fetches
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
 * @property-read Member|null $thereDriver
 * @property-read Member|null $backDriver
 * @property-read Collection<int, EventSkip> $skips
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
        'household_id', 'member_id', 'there_member_id', 'back_member_id', 'kind', 'title', 'starts_on', 'ends_on', 'start_time', 'end_time',
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

    /**
     * Entries that fall, or still repeat, between the two dates (Y-m-d, both inclusive). The same rule
     * in memory is `Services\Sync\EventData::overlaps()`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->where('starts_on', '<=', $to)
            ->where(function (Builder $q) use ($from): void {
                $q->where(function (Builder $q) use ($from): void {
                    $q->where('repeats', self::ONCE)->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$from]);
                })->orWhere(function (Builder $q) use ($from): void {
                    $q->where('repeats', '!=', self::ONCE)
                        ->where(fn (Builder $q) => $q->whereNull('repeat_until')->orWhere('repeat_until', '>=', $from));
                });
            });
    }

    public function repeats(): bool
    {
        return $this->repeats !== self::ONCE;
    }

    public function isFromFeed(): bool
    {
        return $this->feed_id !== null;
    }

    /** A child's activity (ballet, a party): the kind of entry that needs a lift there and back. */
    public function isForChild(): bool
    {
        return $this->member !== null && $this->member->isChild();
    }

    /** Whether single days of it can fall away (a repeating entry of Tribe's own). */
    public function canSkipDays(): bool
    {
        return $this->repeats() && ! $this->isFromFeed();
    }

    /** Whether the page shows its coming days: for lifts, or to take a day out. */
    public function hasComingDays(): bool
    {
        return $this->isForChild() || $this->canSkipDays();
    }

    /**
     * Who does this leg on that day: that day's exception if there is one (it may be nobody), else the
     * usual driver. Reads the `lifts` already loaded for the dates in question.
     */
    public function driverOn(CarbonImmutable $date, string $direction): ?Member
    {
        $exception = $this->liftOn($date, $direction);

        return $exception !== null ? $exception->member : $this->usualDriver($direction);
    }

    /** That day's exception for this leg, from the loaded `lifts`. */
    public function liftOn(CarbonImmutable $date, string $direction): ?Lift
    {
        return $this->lifts->first(fn (Lift $l) => $l->direction === $direction && $l->date->isSameDay($date));
    }

    /** Who may change it: anyone in the family; a helper only their household's own. */
    public function isEditableBy(Member $member): bool
    {
        return ! $this->isFromFeed() && (! $member->isHelper() || ! $this->isFamilyWide());
    }

    /**
     * The kinds someone may add: helpers add their household's appointments and school days;
     * gatherings and anniversaries are the family's.
     *
     * @return list<string>
     */
    public static function kindsFor(Member $member): array
    {
        return $member->isHelper() ? [self::EVENT, self::SCHOOL] : self::KINDS;
    }

    /** @return HasMany<Lift, $this> */
    public function lifts(): HasMany
    {
        return $this->hasMany(Lift::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function thereDriver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'there_member_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function backDriver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'back_member_id');
    }

    /** Who usually does this leg. */
    public function usualDriver(string $direction): ?Member
    {
        return $direction === Lift::THERE ? $this->thereDriver : $this->backDriver;
    }

    /** @return HasMany<EventSkip, $this> */
    public function skips(): HasMany
    {
        return $this->hasMany(EventSkip::class);
    }

    /** Whether this day of the series was taken out. */
    public function isSkippedOn(CarbonImmutable $date): bool
    {
        return $this->skips->contains(fn (EventSkip $s) => $s->date->isSameDay($date));
    }

    /** A link that opens the place in the Maps app. */
    public function mapUrl(): ?string
    {
        return $this->location === null ? null : 'https://maps.apple.com/?q='.rawurlencode($this->location);
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

        return $this->end_time === null ? $this->startLabel() : $this->startLabel().'–'.$this->endLabel();
    }

    public function startLabel(): ?string
    {
        return $this->start_time === null ? null : substr($this->start_time, 0, 5);
    }

    public function endLabel(): ?string
    {
        return $this->end_time === null ? null : substr($this->end_time, 0, 5);
    }

    /** "Sondag 18 Oktober 12:30": the day it starts, and the time if it has one. */
    public function whenLabel(): string
    {
        return trim($this->starts_on->translatedFormat('l j F').' '.$this->timeLabel());
    }
}
