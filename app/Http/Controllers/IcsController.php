<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
use App\Services\Sync\EventData;
use App\Services\Sync\TwoWaySync;
use App\Support\Ics\IcsWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * A member's calendar as an .ics feed, for the iPhone Calendar app (read-only). The secret in the link
 * is the only key, as calendar apps can't log in; it shows what that member sees in Tribe.
 */
class IcsController extends Controller
{
    public function __invoke(string $token): Response
    {
        $member = Member::query()->where('calendar_token', $token)->first();
        abort_if($member === null || strlen($token) !== 40 || ! $member->canLogIn(), 404);

        $ics = new IcsWriter('Tribe');

        $events = Event::query()->visibleTo($member)->with(['hostHousehold', 'skips'])
            ->between(CarbonImmutable::today()->subMonths(3)->toDateString(), CarbonImmutable::today()->addYears(5)->toDateString())
            ->get();

        foreach ($events as $event) {
            $ics->add([
                'uid' => 'event-'.TwoWaySync::marker($event->id),
                'title' => $event->isGathering() && $event->hostHousehold
                    ? $event->title.' ('.$event->hostHousehold->name.')'
                    : $event->title,
                'date' => $event->starts_on,
                'end_date' => $event->ends_on,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'notes' => $event->notes,
                'rrule' => EventData::fromEvent($event)->rrule(),
                'exdates' => $event->skips->map(fn ($s) => $s->date)->all(),
                'url' => route('events.show', $event),
            ]);
        }

        foreach (Member::query()->whereNotNull('birthday')->get() as $person) {
            if ($person->birthday === null) {
                continue;
            }
            $ics->add([
                'uid' => 'birthday-'.TwoWaySync::marker($person->id),
                'title' => __(':name verjaar', ['name' => $person->name]),
                'date' => $person->birthday,
                'rrule' => 'FREQ=YEARLY',
            ]);
        }

        return response($ics->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="tribe.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
