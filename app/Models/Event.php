<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A calendar entry. Kinds: an ordinary event, a yearly anniversary, a school holiday or term, or a
 * gathering (always whole-family, with RSVPs per household and a who-brings-what list).
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
 * @property bool $yearly
 * @property int|null $host_household_id
 * @property int|null $created_by
 * @property-read Household|null $household
 * @property-read Member|null $member
 * @property-read Household|null $hostHousehold
 */
class Event extends Model
{
    use HouseholdScoped;

    public const EVENT = 'event';

    public const ANNIVERSARY = 'anniversary';

    public const SCHOOL = 'school';

    public const GATHERING = 'gathering';

    public const KINDS = [self::EVENT, self::GATHERING, self::ANNIVERSARY, self::SCHOOL];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => self::EVENT, 'yearly' => false];

    protected $fillable = [
        'household_id', 'member_id', 'kind', 'title', 'starts_on', 'ends_on', 'start_time', 'end_time',
        'location', 'notes', 'yearly', 'host_household_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'yearly' => 'boolean',
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

    public function isGathering(): bool
    {
        return $this->kind === self::GATHERING;
    }

    public function colour(): string
    {
        return $this->member?->displayColour()
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
