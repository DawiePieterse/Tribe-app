<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
 */
class PushSubscription extends Model
{
    protected $fillable = ['member_id', 'endpoint', 'p256dh', 'auth', 'failures'];
}
