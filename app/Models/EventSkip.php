<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One day a repeating entry falls away (practice cancelled, school closed); the rest of the series stays.
 *
 * @property int $id
 * @property int $event_id
 * @property CarbonImmutable $date
 */
class EventSkip extends Model
{
    protected $fillable = ['event_id', 'date'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
