<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;

/** What two-way sync needs from Google, Outlook or iCloud. Failures throw, with a reason people can read. */
interface CalendarProvider
{
    /** @return list<array{id: string, name: string, primary: bool}> calendars Tribe may write to */
    public function calendars(CalendarConnection $connection): array;

    /** @return list<RemoteEvent> events that happen (or still repeat) between the two dates */
    public function events(CalendarConnection $connection, string $from, string $to): array;

    public function create(CalendarConnection $connection, EventData $data, int $tribeId): RemoteEvent;

    public function update(CalendarConnection $connection, RemoteEvent $remote, EventData $data, int $tribeId): RemoteEvent;

    /** Already gone counts as done. */
    public function delete(CalendarConnection $connection, string $remoteId): void;
}
