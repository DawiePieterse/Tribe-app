<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $date_poll_id
 * @property CarbonImmutable $date
 * @property string|null $time
 * @property-read DatePoll $poll
 */
class DatePollOption extends Model
{
    protected $fillable = ['date_poll_id', 'date', 'time'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    /** @return BelongsTo<DatePoll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(DatePoll::class, 'date_poll_id');
    }

    /** @return HasMany<DatePollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(DatePollVote::class);
    }

    /** Yes counts 2, maybe 1, so the date that suits most households comes first. */
    public function score(): int
    {
        return $this->votes->sum(fn (DatePollVote $v) => match ($v->answer) {
            DatePollVote::YES => 2,
            DatePollVote::MAYBE => 1,
            default => 0,
        });
    }
}
