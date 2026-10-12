<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $date_poll_option_id
 * @property int $household_id
 * @property string $answer
 */
class DatePollVote extends Model
{
    public const YES = 'yes';

    public const MAYBE = 'maybe';

    public const NO = 'no';

    protected $fillable = ['date_poll_option_id', 'household_id', 'answer', 'voted_by'];
}
