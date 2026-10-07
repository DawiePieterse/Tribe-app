<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One household's answer to a gathering.
 *
 * @property int $id
 * @property int $event_id
 * @property int $household_id
 * @property string $status
 * @property int $adults
 * @property int $children
 * @property string|null $note
 * @property-read Household $household
 */
class GatheringResponse extends Model
{
    public const YES = 'yes';

    public const NO = 'no';

    public const MAYBE = 'maybe';

    /** @var array<string, mixed> */
    protected $attributes = ['adults' => 0, 'children' => 0];

    protected $fillable = ['event_id', 'household_id', 'status', 'adults', 'children', 'note', 'responded_by'];

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
