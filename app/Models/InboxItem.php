<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message someone sent to Tribe from the share sheet ("Stuur na Tribe"), with the event Tribe read
 * out of it. It waits on the home screen until they add it to the calendar or throw it away.
 *
 * @property int $id
 * @property int $member_id
 * @property string $text
 * @property array<string, mixed>|null $suggestion
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable $created_at
 */
class InboxItem extends Model
{
    protected $fillable = ['member_id', 'text', 'suggestion', 'handled_at'];

    protected function casts(): array
    {
        return ['suggestion' => 'array', 'handled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
