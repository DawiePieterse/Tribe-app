<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Models\Event;

/**
 * After someone changes an event in Tribe, connected calendars catch up as soon as the page has
 * been sent (the scheduler would otherwise get to it within ten minutes).
 */
final class SyncSoon
{
    public static function watch(): void
    {
        $ask = function (Event $event): void {
            // Asked once per request; the flag lives in the container so it never outlasts the request.
            if (! TwoWaySync::$running && $event->feed_id === null && ! app()->bound('tribe.sync-soon')) {
                app()->instance('tribe.sync-soon', true);
                app()->terminating(function (): void {
                    foreach (CalendarConnection::query()->whereNotNull('calendar_id')->with('member')->get() as $connection) {
                        app(TwoWaySync::class)->sync($connection);
                    }
                });
            }
        };

        Event::saved($ask);
        Event::deleted($ask);
    }
}
