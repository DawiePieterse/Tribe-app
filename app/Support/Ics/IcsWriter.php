<?php

namespace App\Support\Ics;

use Carbon\CarbonImmutable;

/**
 * Builds an iCalendar file for the iPhone (or any) calendar app. Times are written in UTC. Without a
 * name it is a single calendar object, as CalDAV stores one event per file.
 */
final class IcsWriter
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(?string $name)
    {
        $this->lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Tribe//Familie//AF', 'CALSCALE:GREGORIAN'];

        if ($name === null) {
            return;
        }

        $this->lines = [
            ...$this->lines,
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
            'X-WR-TIMEZONE:Africa/Johannesburg',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];
    }

    /**
     * @param  array{uid: string, title: string, date: CarbonImmutable, end_date?: CarbonImmutable|null, start_time?: string|null, end_time?: string|null, location?: string|null, notes?: string|null, rrule?: string|null, url?: string|null}  $e
     */
    public function add(array $e): void
    {
        $this->lines[] = 'BEGIN:VEVENT';
        $this->lines[] = 'UID:'.$e['uid'];
        $this->lines[] = 'DTSTAMP:'.CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        $this->lines[] = 'SUMMARY:'.$this->escape($e['title']);

        $startTime = $e['start_time'] ?? null;
        $endDate = $e['end_date'] ?? null;

        if ($startTime === null) {
            $this->lines[] = 'DTSTART;VALUE=DATE:'.$e['date']->format('Ymd');
            $this->lines[] = 'DTEND;VALUE=DATE:'.($endDate ?? $e['date'])->addDay()->format('Ymd');
        } else {
            $start = $this->at($e['date'], $startTime);
            $end = ($e['end_time'] ?? null) !== null ? $this->at($endDate ?? $e['date'], (string) $e['end_time']) : $start->addHour();
            $this->lines[] = 'DTSTART:'.$start->format('Ymd\THis\Z');
            $this->lines[] = 'DTEND:'.($end->greaterThan($start) ? $end : $start->addHour())->format('Ymd\THis\Z');
        }

        foreach (['location' => 'LOCATION', 'notes' => 'DESCRIPTION', 'url' => 'URL'] as $key => $prop) {
            if (! empty($e[$key])) {
                $this->lines[] = $prop.':'.($key === 'url' ? $e[$key] : $this->escape((string) $e[$key]));
            }
        }

        if (! empty($e['rrule'])) {
            $this->lines[] = 'RRULE:'.$e['rrule'];
        }

        $this->lines[] = 'END:VEVENT';
    }

    public function render(): string
    {
        $out = '';
        foreach ([...$this->lines, 'END:VCALENDAR'] as $line) {
            $out .= $this->fold($line)."\r\n";
        }

        return $out;
    }

    private function at(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.substr($time, 0, 5), 'Africa/Johannesburg')->utc();
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines longer than 75 bytes continue on the next line after a space, without splitting a character. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $chunk = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($chunk.$char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$chunk;
                $chunk = '';
            }
            $chunk .= $char;
        }

        return $out."\r\n ".$chunk;
    }
}
