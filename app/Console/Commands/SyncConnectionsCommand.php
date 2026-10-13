<?php

namespace App\Console\Commands;

use App\Models\CalendarConnection;
use App\Services\Sync\TwoWaySync;
use Illuminate\Console\Command;

/** Runs every ten minutes from the scheduler: two-way sync with everyone's connected calendars. */
class SyncConnectionsCommand extends Command
{
    protected $signature = 'tribe:sync-connections';

    protected $description = 'Two-way sync with every connected Google, Outlook and iCloud calendar';

    public function handle(TwoWaySync $sync): int
    {
        foreach (CalendarConnection::query()->ready()->with('member')->orderBy('id')->get() as $connection) {
            $ok = $sync->sync($connection);
            $this->line(($ok ? 'ok   ' : 'fail ').$connection->providerName().' '.$connection->account.($ok ? '' : ': '.$connection->last_error));
        }

        return self::SUCCESS;
    }
}
