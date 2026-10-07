<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use link that logs one adult in. Admins share it on WhatsApp.
 *
 * @property int $id
 * @property int $member_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property-read Member $member
 */
class Invite extends Model
{
    public const VALID_DAYS = 14;

    protected $fillable = ['member_id', 'token_hash', 'expires_at', 'used_at', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
