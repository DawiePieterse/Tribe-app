<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Models\Event;

/**
 * After someone changes an event in Tribe, the connected calendars that can see it catch up as soon
 * as the page has been sent (the scheduler would otherwise get to it within ten minutes).
 */
final class SyncSoon
{
    /** Container keys: writes a sync itself makes don't ask for another; one catch-up per request. */
    private const QUIET = 'tribe.sync.quiet';

    private const ASKED = 'tribe.sync.asked';

    public static function watch(): void
    {
        $ask = function (Event $event): void {
            if ($event->feed_id !== null || app()->bound(self::QUIET) || app()->bound(self::ASKED)) {
                return;
            }

            app()->instance(self::ASKED, true);
            app()->terminating(fn () => self::catchUp($event));
        };

        Event::saved($ask);
        Event::deleted($ask);
    }

    /** Runs $fn without its event writes asking for a catch-up. */
    public static function quietly(callable $fn): mixed
    {
        $was = app()->bound(self::QUIET);
        app()->instance(self::QUIET, true);

        try {
            return $fn();
        } finally {
            if (! $was) {
                app()->forgetInstance(self::QUIET);
            }
        }
    }

    private static function catchUp(Event $event): void
    {
        app()->forgetInstance(self::ASKED);

        $connections = CalendarConnection::query()->ready()->with('member')
            // Only the people who may see the event (a deleted event still has its household_id).
            ->when($event->household_id !== null, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('household_id', $event->household_id)))
            ->get();

        foreach ($connections as $connection) {
            app(TwoWaySync::class)->sync($connection);
        }
    }
}
