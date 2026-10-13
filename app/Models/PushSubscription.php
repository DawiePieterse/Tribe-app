<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One device of one member that asked for notifications. The endpoint is the push service's address
 * for that device; the keys encrypt the message to it.
 *
 * @property int $id
 * @property int $member_id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property int $failures
 * @property-read Member $member
 */
class PushSubscription extends Model
{
    protected $fillable = ['member_id', 'endpoint', 'p256dh', 'auth', 'failures'];

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
