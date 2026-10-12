<?php

use App\Services\Messages\SimpleReader;
use Carbon\CarbonImmutable;

// Wednesday 7 October 2026.
function readMessage(string $text): array
{
    return (new SimpleReader)->read($text, CarbonImmutable::parse('2026-10-07'))->toArray();
}

it('reads an Afrikaans invitation', function () {
    expect(readMessage("Hallo almal! Ouma nooi julle vir middagete Sondag om 12:30 😊\nBring asb ’n slaai"))
        ->toMatchArray(['title' => 'Ouma nooi julle vir middagete Sondag om 12:30', 'kind' => 'gathering', 'starts_on' => '2026-10-11', 'start_time' => '12:30']);
});

it('reads dates in several forms', function (string $text, string $date) {
    expect(readMessage($text)['starts_on'])->toBe($date);
})->with([
    ['Tandarts 18 Okt', '2026-10-18'],
    ['Konsert op 3 November 2026', '2026-11-03'],
    ['Ouerdag 20/10', '2026-10-20'],
    ['Vakansie begin 5 Jan', '2027-01-05'],
    ['Braai more', '2026-10-08'],
    ['Kuier oormôre', '2026-10-09'],
    ['Rugby Woensdag', '2026-10-14'],
    ['Volgende Saterdag swem', '2026-10-17'],
    ['Saterdag swem', '2026-10-10'],
]);

it('reads times the way families say them', function (string $text, ?string $time) {
    expect(readMessage($text)['start_time'])->toBe($time);
})->with([
    ['Ete Sondag half een', '12:30'],
    ['Koffie om 3', '15:00'],
    ['Braai 6nm', '18:00'],
    ['Ontbyt 8uur', '08:00'],
    ['Partytjie 14h00 tot 16h30', '14:00'],
    ['Dokter Dinsdag', null],
]);

it('keeps an ordinary appointment as an event', function () {
    expect(readMessage('Ben se tandarts Dinsdag 09:15')['kind'])->toBe('event');
});
