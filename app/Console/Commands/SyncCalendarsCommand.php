<?php

namespace App\Console\Commands;

use App\Models\CalendarFeed;
use App\Services\CalendarSync;
use Illuminate\Console\Command;

/** Runs hourly from the scheduler (cPanel cron: `php artisan schedule:run` every minute, or this hourly). */
class SyncCalendarsCommand extends Command
{
    protected $signature = 'tribe:sync-calendars';

    protected $description = 'Fetch every subscribed calendar and update its events';

    public function handle(CalendarSync $sync): int
    {
        foreach (CalendarFeed::query()->orderBy('id')->get() as $feed) {
            $ok = $sync->sync($feed);
            $this->line(($ok ? 'ok   ' : 'fail ').$feed->name.($ok ? '' : ': '.$feed->last_error));
        }

        return self::SUCCESS;
    }
}
