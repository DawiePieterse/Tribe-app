<?php

namespace App\Models\Concerns;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that belong either to one household (household_id set: only that household sees them)
 * or to the whole family (household_id null). This is the one place the privacy rule lives.
 *
 * @property int|null $household_id
 */
trait HouseholdScoped
{
    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, Member $member): Builder
    {
        return $query->where(function (Builder $q) use ($member): void {
            $q->whereNull($this->qualifyColumn('household_id'))
                ->orWhere($this->qualifyColumn('household_id'), $member->household_id);
        });
    }

    public function isVisibleTo(Member $member): bool
    {
        return $this->household_id === null || $this->household_id === $member->household_id;
    }

    public function isFamilyWide(): bool
    {
        return $this->household_id === null;
    }
}
