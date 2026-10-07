<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $member_id
 * @property string $code_hash
 * @property CarbonImmutable $expires_at
 * @property int $attempts
 * @property-read Member $member
 */
class LoginCode extends Model
{
    public const MAX_ATTEMPTS = 5;

    public const VALID_MINUTES = 15;

    /** @var array<string, mixed> */
    protected $attributes = ['attempts' => 0];

    protected $fillable = ['member_id', 'code_hash', 'expires_at', 'attempts'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Codes are stored as an HMAC under the app key, never in the clear. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
