<?php

use App\Support\Ics\IcsParser;
use App\Support\Ics\IcsWriter;
use Carbon\CarbonImmutable;

function ics(string $events): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/London\r\nBEGIN:STANDARD\r\nDTSTART:19701025T020000\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n".$events."END:VCALENDAR\r\n";
}

it('reads all-day, timed, UTC and folded events', function () {
    $events = (new IcsParser)->events(ics(
        "BEGIN:VEVENT\r\nUID:a1\r\nSUMMARY:Skoolvakansie\r\nDTSTART;VALUE=DATE:20261205\r\nDTEND;VALUE=DATE:20270114\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:a2\r\nSUMMARY:Ouerdag\\, saal\r\nDTSTART:20261020T120000Z\r\nDTEND:20261020T133000Z\r\nLOCATION:Groot \r\n saal\r\n"
        ."DESCRIPTION:Reël een\\nReël twee\r\nBEGIN:VALARM\r\nDESCRIPTION:Herinnering\r\nEND:VALARM\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:a3\r\nSUMMARY:Konsert\r\nDTSTART;TZID=Africa/Johannesburg:20261101T180000\r\nDTEND;TZID=Africa/Johannesburg:20261101T200000\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:a4\r\nSUMMARY:Gekanselleer\r\nSTATUS:CANCELLED\r\nDTSTART;VALUE=DATE:20261101\r\nEND:VEVENT\r\n"
    ));

    expect($events)->toHaveCount(3);
    expect($events[0])->toMatchArray(['title' => 'Skoolvakansie', 'starts_on' => '2026-12-05', 'ends_on' => '2027-01-13', 'start_time' => null]);
    expect($events[1])->toMatchArray(['title' => 'Ouerdag, saal', 'start_time' => '14:00', 'end_time' => '15:30', 'location' => 'Groot saal', 'notes' => "Reël een\nReël twee"]);
    expect($events[2])->toMatchArray(['start_time' => '18:00', 'end_time' => '20:00']);
});

it('reads simple repeats and leaves complicated ones single', function () {
    $events = (new IcsParser)->events(ics(
        "BEGIN:VEVENT\r\nUID:r1\r\nSUMMARY:Rugby\r\nDTSTART:20261006T130000Z\r\nRRULE:FREQ=WEEKLY;BYDAY=TU;UNTIL=20261124T130000Z\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:r2\r\nSUMMARY:Koor\r\nDTSTART;VALUE=DATE:20261004\r\nRRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=3\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:r3\r\nSUMMARY:Swem\r\nDTSTART;VALUE=DATE:20261005\r\nRRULE:FREQ=WEEKLY;BYDAY=MO,WE\r\nEND:VEVENT\r\n"
    ));

    expect($events[0])->toMatchArray(['repeats' => 'weekly', 'repeat_until' => '2026-11-24']);
    expect($events[1])->toMatchArray(['repeats' => 'fortnightly', 'repeat_until' => '2026-11-01']);
    expect($events[2])->toMatchArray(['repeats' => 'none', 'repeat_until' => null]);
});

it('writes a calendar with UTC times, escaping and folded lines', function () {
    $ics = new IcsWriter('Tribe');
    $ics->add(['uid' => 'e1@x', 'title' => 'Sondagete, by Ouma; almal', 'date' => CarbonImmutable::parse('2026-10-11'), 'start_time' => '12:30:00', 'rrule' => 'FREQ=WEEKLY']);
    $ics->add(['uid' => 'e2@x', 'title' => str_repeat('Baie lang naam ', 10), 'date' => CarbonImmutable::parse('2026-12-05'), 'end_date' => CarbonImmutable::parse('2026-12-07')]);
    $out = $ics->render();

    expect($out)->toContain("SUMMARY:Sondagete\\, by Ouma\; almal\r\n")
        ->toContain("DTSTART:20261011T103000Z\r\n")
        ->toContain("DTEND:20261011T113000Z\r\n")
        ->toContain("RRULE:FREQ=WEEKLY\r\n")
        ->toContain("DTSTART;VALUE=DATE:20261205\r\nDTEND;VALUE=DATE:20261208\r\n");

    foreach (explode("\r\n", $out) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    // What Tribe writes, Tribe can read back.
    expect((new IcsParser)->events($out)[0])->toMatchArray(['title' => 'Sondagete, by Ouma; almal', 'start_time' => '12:30', 'repeats' => 'weekly']);
});
