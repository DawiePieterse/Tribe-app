<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's own calendar (Google, Outlook or iCloud), kept in step with Tribe both ways by
 * `Services\Sync\TwoWaySync`. household_id says who sees the events that come in from it.
 *
 * @property int $id
 * @property int $member_id
 * @property string $provider
 * @property string|null $account
 * @property array<string, mixed> $credentials
 * @property string|null $calendar_id
 * @property string|null $calendar_name
 * @property string $send
 * @property CarbonImmutable|null $synced_at
 * @property string|null $last_error
 * @property-read Member $member
 */
class CalendarConnection extends Model
{
    use HouseholdScoped;

    public const GOOGLE = 'google';

    public const OUTLOOK = 'outlook';

    public const ICLOUD = 'icloud';

    public const PROVIDERS = [self::GOOGLE, self::OUTLOOK, self::ICLOUD];

    /** Those that sign in with OAuth (and so need an app registered with them); iCloud uses a password. */
    public const OAUTH = [self::GOOGLE, self::OUTLOOK];

    /** Send everything the member sees in Tribe, or only their own household's events. */
    public const SEND_ALL = 'all';

    public const SEND_OURS = 'ours';

    /** @var array<string, mixed> */
    protected $attributes = ['send' => self::SEND_ALL];

    protected $fillable = ['member_id', 'provider', 'account', 'credentials', 'calendar_id', 'calendar_name', 'household_id', 'send', 'synced_at', 'last_error'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'synced_at' => 'immutable_datetime'];
    }

    /**
     * Connections that are set up (a calendar chosen) and so take part in syncs.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->whereNotNull('calendar_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return HasMany<EventLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EventLink::class, 'connection_id');
    }

    public function providerName(): string
    {
        return self::label($this->provider);
    }

    public static function label(string $provider): string
    {
        return [self::GOOGLE => 'Google', self::OUTLOOK => 'Outlook', self::ICLOUD => 'iCloud'][$provider] ?? $provider;
    }

    /** OAuth providers need an app registered with them first (see docs/DEPLOY-AFRIHOST.md). */
    public static function isAvailable(string $provider): bool
    {
        return ! in_array($provider, self::OAUTH, true)
            || (filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")));
    }

    /** Where "Koppel …" takes someone: off to sign in, or to the iCloud password form. */
    public static function connectUrl(string $provider): string
    {
        return in_array($provider, self::OAUTH, true) ? route('connections.connect', $provider) : route('connections.icloud');
    }
}
