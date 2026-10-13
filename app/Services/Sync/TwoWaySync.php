<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Models\Event;
use App\Models\EventLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps Tribe and a person's own calendar in step, both ways. Each event on both sides has an
 * EventLink holding what both sides looked like after the last sync; comparing tells which side
 * changed. When both changed, the later change wins.
 *
 * - New in their calendar: added to Tribe (seen by their household, or the whole family).
 * - New in Tribe (anything they see, or only their household's): added to their calendar.
 * - Changed on one side: changed on the other.
 * - Deleted in Tribe: deleted from their calendar.
 * - Deleted from their calendar: deleted from Tribe if it came from there; a Tribe event someone
 *   took out of their own calendar stays in Tribe for everyone else and isn't sent to them again.
 */
class TwoWaySync
{
    private const MONTHS_BACK = 1;

    private const MONTHS_AHEAD = 18;

    /** New events sent out in one run, so a first sync can't run past the server's time limit. */
    private const MAX_NEW = 300;

    /** True while a sync writes Tribe events, so those writes don't ask for another sync. */
    public static bool $running = false;

    public function __construct(private ProviderFactory $providers) {}

    /** @return bool whether it worked; the reason it didn't is kept on the connection. */
    public function sync(CalendarConnection $connection): bool
    {
        if ($connection->calendar_id === null) {
            return false;
        }

        $connection->loadMissing('member');
        self::$running = true;

        try {
            $this->run($connection, $this->providers->for($connection->provider));
        } catch (\Throwable $e) {
            report($e);
            $connection->update(['last_error' => mb_substr($e->getMessage(), 0, 250)]);

            return false;
        } finally {
            self::$running = false;
        }

        $connection->update(['synced_at' => now(), 'last_error' => null]);

        return true;
    }

    /** "12@tribe.example" — which Tribe event a copy belongs to, and that it came from this Tribe. */
    public static function marker(int $eventId): string
    {
        return $eventId.'@'.self::host();
    }

    public static function tribeId(?string $marker): ?int
    {
        if ($marker === null || ! preg_match('/^(\d+)@(.+)$/', $marker, $m) || strcasecmp($m[2], self::host()) !== 0) {
            return null;
        }

        return (int) $m[1];
    }

    private static function host(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'tribe');
    }

    private function run(CalendarConnection $connection, CalendarProvider $provider): void
    {
        $member = $connection->member;
        $from = CarbonImmutable::today()->subMonths(self::MONTHS_BACK)->toDateString();
        $to = CarbonImmutable::today()->addMonths(self::MONTHS_AHEAD)->toDateString();

        $remote = [];
        foreach ($provider->events($connection, $from, $to) as $event) {
            $remote[$event->id] = $event;
        }

        /** @var Collection<int, EventLink> $links */
        $links = $connection->links()->with('event')->get();
        $byRemote = $links->whereNotNull('remote_id')->keyBy('remote_id');
        $seen = [];

        // 1. What their calendar has.
        foreach ($remote as $id => $theirs) {
            $link = $byRemote->get($id);

            if ($link === null) {
                $this->arrived($connection, $provider, $theirs);

                continue;
            }

            $seen[$link->id] = true;
            $event = $link->event;

            // Deleted in Tribe, or no longer something this person may see: take it out there too.
            if ($event === null || ! $event->isVisibleTo($member) || $event->isFromFeed()) {
                $provider->delete($connection, $id);
                $link->delete();

                continue;
            }

            $ours = EventData::fromEvent($event);
            $theyChanged = $theirs->version !== $link->remote_version;
            $weChanged = $ours->hash() !== $link->local_hash;

            if ($theyChanged && (! $weChanged || $this->isLater($theirs, $event))) {
                $event->update($theirs->data->attributes());
                $link->update(['remote_version' => $theirs->version, 'local_hash' => EventData::fromEvent($event)->hash()]);
            } elseif ($weChanged) {
                $updated = $provider->update($connection, $theirs, $ours, $event->id);
                $link->update(['remote_version' => $updated->version, 'local_hash' => $ours->hash()]);
            }
        }

        // 2. Links whose event is no longer in their calendar.
        foreach ($links as $link) {
            if (isset($seen[$link->id]) || $link->remote_id === null) {
                if ($link->remote_id === null && $link->event === null) {
                    $link->delete();
                }

                continue;
            }

            $event = $link->event;
            if ($event === null) {
                // Gone on both sides.
                $link->delete();
            } elseif (! EventData::fromEvent($event)->overlaps($from, $to)) {
                // Moved outside the dates looked at; it wasn't deleted.
                continue;
            } elseif ($link->origin === EventLink::FROM_REMOTE) {
                $event->delete();
                $link->delete();
            } else {
                $link->update(['remote_id' => null, 'remote_version' => null]);
            }
        }

        // 3. Tribe events their calendar doesn't have yet.
        $linked = $connection->links()->whereNotNull('event_id')->pluck('event_id');
        $new = Event::query()->visibleTo($member)
            ->whereNull('feed_id')
            ->whereNotIn('id', $linked)
            ->where('starts_on', '<=', $to)
            ->where(fn ($q) => $q->where(fn ($r) => $r->where('repeats', '!=', Event::ONCE)->where(fn ($u) => $u->whereNull('repeat_until')->orWhere('repeat_until', '>=', $from)))
                ->orWhereRaw('COALESCE(ends_on, starts_on) >= ?', [$from]))
            ->when($connection->send === CalendarConnection::SEND_OURS, fn ($q) => $q->where(fn ($r) => $r
                ->where('household_id', $member->household_id)
                ->orWhere('host_household_id', $member->household_id)
                ->orWhere('member_id', $member->id)
                ->orWhere('created_by', $member->id)))
            ->orderBy('starts_on')
            ->limit(self::MAX_NEW)
            ->get();

        foreach ($new as $event) {
            $data = EventData::fromEvent($event);
            $created = $provider->create($connection, $data, $event->id);
            EventLink::query()->create([
                'connection_id' => $connection->id,
                'event_id' => $event->id,
                'remote_id' => $created->id,
                'remote_version' => $created->version,
                'local_hash' => $data->hash(),
                'origin' => EventLink::FROM_TRIBE,
            ]);
        }
    }

    /** An event in their calendar that this connection hasn't linked yet. */
    private function arrived(CalendarConnection $connection, CalendarProvider $provider, RemoteEvent $theirs): void
    {
        $member = $connection->member;
        $event = $this->alreadyInTribe($connection, $theirs);

        if ($event !== null) {
            // A copy Tribe made earlier (or the same shared calendar connected by someone else): link,
            // and let Tribe's version stand.
            $ours = EventData::fromEvent($event);
            $version = $theirs->version;
            if ($ours->hash() !== $theirs->data->hash()) {
                $version = $provider->update($connection, $theirs, $ours, $event->id)->version;
            }
            EventLink::query()->create([
                'connection_id' => $connection->id,
                'event_id' => $event->id,
                'remote_id' => $theirs->id,
                'remote_version' => $version,
                'local_hash' => $ours->hash(),
                'origin' => EventLink::FROM_TRIBE,
            ]);

            return;
        }

        DB::transaction(function () use ($connection, $member, $theirs): void {
            $event = Event::query()->create($theirs->data->attributes() + [
                'kind' => Event::EVENT,
                'household_id' => $connection->household_id,
                'member_id' => $member->id,
                'created_by' => $member->id,
            ]);
            EventLink::query()->create([
                'connection_id' => $connection->id,
                'event_id' => $event->id,
                'remote_id' => $theirs->id,
                'remote_version' => $theirs->version,
                'local_hash' => EventData::fromEvent($event)->hash(),
                'origin' => EventLink::FROM_REMOTE,
            ]);
        });
    }

    private function alreadyInTribe(CalendarConnection $connection, RemoteEvent $theirs): ?Event
    {
        $linkedHere = fn (int $eventId) => $connection->links()->where('event_id', $eventId)->exists();

        if ($theirs->tribeId !== null) {
            $event = Event::query()->find($theirs->tribeId);
            if ($event !== null && $event->isVisibleTo($connection->member) && ! $event->isFromFeed() && ! $linkedHere($event->id)) {
                return $event;
            }
        }

        $other = EventLink::query()->with('event')
            ->where('remote_id', $theirs->id)
            ->where('connection_id', '!=', $connection->id)
            ->whereHas('connection', fn ($q) => $q->where('provider', $connection->provider)->where('calendar_id', $connection->calendar_id))
            ->first();

        $event = $other?->event;

        return $event !== null && $event->isVisibleTo($connection->member) && ! $linkedHere($event->id) ? $event : null;
    }

    private function isLater(RemoteEvent $theirs, Event $event): bool
    {
        return $theirs->updatedAt === null || $event->updated_at === null || $theirs->updatedAt->greaterThan($event->updated_at);
    }
}
