<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $member_id
 * @property CarbonImmutable $happened_on
 * @property string $title
 * @property-read Member $member
 */
class Milestone extends Model
{
    protected $fillable = ['member_id', 'happened_on', 'title'];

    protected function casts(): array
    {
        return ['happened_on' => 'immutable_date'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
