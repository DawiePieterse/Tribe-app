<?php

use Illuminate\Support\Facades\Schedule;

// Commands in app/Console/Commands are discovered automatically. On Afrihost a cPanel cron job runs
// `php artisan schedule:run` (see docs/DEPLOY-AFRIHOST.md).

Schedule::command('tribe:sync-calendars')->hourly()->withoutOverlapping();
Schedule::command('tribe:sync-connections')->everyTenMinutes()->withoutOverlapping();
Schedule::command('tribe:remind')->dailyAt('18:00')->withoutOverlapping();
