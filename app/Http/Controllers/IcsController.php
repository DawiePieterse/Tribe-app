<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
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

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'tribe';
        $since = CarbonImmutable::today()->subMonths(3)->toDateString();
        $ics = new IcsWriter('Tribe');

        $events = Event::query()->visibleTo($member)->with('hostHousehold')
            ->where(fn ($q) => $q->where('repeats', '!=', Event::ONCE)->orWhereRaw('COALESCE(ends_on, starts_on) >= ?', [$since]))
            ->get();

        foreach ($events as $event) {
            $rule = match ($event->repeats) {
                Event::WEEKLY => 'FREQ=WEEKLY',
                Event::FORTNIGHTLY => 'FREQ=WEEKLY;INTERVAL=2',
                Event::MONTHLY => 'FREQ=MONTHLY',
                Event::YEARLY => 'FREQ=YEARLY',
                default => null,
            };
            if ($rule !== null && $event->repeat_until !== null) {
                $rule .= ';UNTIL='.$event->repeat_until->format('Ymd').'T215959Z';
            }

            $ics->add([
                'uid' => 'event-'.$event->id.'@'.$host,
                'title' => $event->isGathering() && $event->hostHousehold
                    ? $event->title.' ('.$event->hostHousehold->name.')'
                    : $event->title,
                'date' => $event->starts_on,
                'end_date' => $event->ends_on,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'notes' => $event->notes,
                'rrule' => $rule,
                'url' => route('events.show', $event),
            ]);
        }

        foreach (Member::query()->whereNotNull('birthday')->get() as $person) {
            if ($person->birthday === null) {
                continue;
            }
            $ics->add([
                'uid' => 'birthday-'.$person->id.'@'.$host,
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
