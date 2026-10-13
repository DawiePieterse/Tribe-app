<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Something a household bought recently, by plain ingredient name (one row per name; buying it again
 * moves the date). Kept for a few weeks, for "what can we make with what we bought?".
 *
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property CarbonImmutable $bought_on
 */
class Grocery extends Model
{
    /** How long a purchase counts as still in the cupboard. */
    public const KEEP_DAYS = 21;

    protected $fillable = ['household_id', 'name', 'bought_on', 'created_by'];

    protected function casts(): array
    {
        return ['bought_on' => 'immutable_date'];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecentFor(Builder $query, Member $member): Builder
    {
        return $query->where('household_id', $member->household_id)
            ->where('bought_on', '>=', CarbonImmutable::today()->subDays(self::KEEP_DAYS)->toDateString());
    }

    /** @param  list<string>  $names */
    public static function record(Member $member, array $names): void
    {
        $today = CarbonImmutable::today()->toDateString();

        static::query()->where('household_id', $member->household_id)
            ->where('bought_on', '<', CarbonImmutable::today()->subDays(self::KEEP_DAYS * 3)->toDateString())->delete();

        static::query()->upsert(
            array_map(fn (string $name) => [
                'household_id' => $member->household_id,
                'name' => mb_substr($name, 0, 60),
                'bought_on' => $today,
                'created_by' => $member->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $names),
            ['household_id', 'name'],
            ['bought_on', 'created_by', 'updated_at'],
        );
    }
}
