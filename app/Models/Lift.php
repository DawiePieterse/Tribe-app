<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who takes a child to an activity (there) and who fetches them (back), on one day of the event.
 * A repeating event (ballet every Tuesday) has a row per date, so this week Pa and next week the nanny.
 *
 * @property int $id
 * @property int $event_id
 * @property CarbonImmutable $date
 * @property string $direction
 * @property int|null $member_id
 * @property-read Member|null $member
 */
class Lift extends Model
{
    public const THERE = 'there';

    public const BACK = 'back';

    public const DIRECTIONS = [self::THERE, self::BACK];

    protected $fillable = ['event_id', 'date', 'direction', 'member_id'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function label(string $direction): string
    {
        return $direction === self::THERE ? __('Neem') : __('Haal');
    }

    public static function key(int $eventId, string $date, string $direction): string
    {
        return $eventId.'|'.$date.'|'.$direction;
    }
}
