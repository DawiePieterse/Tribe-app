<?php

namespace App\Support\Ics;

use Carbon\CarbonImmutable;

/**
 * Reads the VEVENTs from an iCalendar (.ics) file, enough for school terms, sports fixtures and
 * public holidays: dates or times (UTC, a named zone or floating), DTEND, location, description, and
 * simple repeats (weekly, fortnightly, monthly, yearly, with UNTIL or COUNT). Anything fancier repeats
 * only once here rather than wrongly.
 */
final class IcsParser
{
    private const ZONE = 'Africa/Johannesburg';

    /**
     * @return list<array{uid: string, title: string, starts_on: string, ends_on: string|null, start_time: string|null, end_time: string|null, location: string|null, notes: string|null, repeats: string, repeat_until: string|null}>
     */
    public function events(string $ics): array
    {
        $lines = $this->unfold($ics);
        $events = [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];

                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($current !== null && ($event = $this->event($current)) !== null) {
                    $events[] = $event;
                }
                $current = null;

                continue;
            }

            if ($current === null || ! str_contains($line, ':')) {
                continue;
            }

            [$head, $value] = explode(':', $line, 2);
            $parts = explode(';', $head);
            $name = strtoupper(array_shift($parts));
            $params = [];
            foreach ($parts as $part) {
                [$key, $val] = array_pad(explode('=', $part, 2), 2, '');
                $params[strtoupper($key)] = trim($val, '"');
            }

            // Only the first of each property counts (a VALARM inside a VEVENT has its own DESCRIPTION).
            $current[$name] ??= ['value' => $value, 'params' => $params];
        }

        return $events;
    }

    /** @return list<string> */
    private function unfold(string $ics): array
    {
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        $ics = (string) preg_replace("/\n[ \t]/", '', $ics);

        $lines = [];
        $depth = 0;
        foreach (explode("\n", $ics) as $line) {
            // Skip everything nested inside an event (alarms), so its properties don't leak in.
            if (str_starts_with($line, 'BEGIN:') && $line !== 'BEGIN:VEVENT' && $line !== 'BEGIN:VCALENDAR') {
                $depth++;

                continue;
            }
            if (str_starts_with($line, 'END:') && $line !== 'END:VEVENT' && $line !== 'END:VCALENDAR') {
                $depth = max(0, $depth - 1);

                continue;
            }
            if ($depth === 0 && $line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, array{value: string, params: array<string, string>}>  $p
     * @return array{uid: string, title: string, starts_on: string, ends_on: string|null, start_time: string|null, end_time: string|null, location: string|null, notes: string|null, repeats: string, repeat_until: string|null}|null
     */
    private function event(array $p): ?array
    {
        if (! isset($p['DTSTART']) || strtoupper($p['STATUS']['value'] ?? '') === 'CANCELLED') {
            return null;
        }

        $start = $this->moment($p['DTSTART']);
        if ($start === null) {
            return null;
        }

        [$startAt, $allDay] = $start;
        $end = isset($p['DTEND']) ? $this->moment($p['DTEND']) : null;
        $endAt = $end[0] ?? null;

        // An all-day DTEND is the day after the last day.
        if ($allDay && $endAt !== null) {
            $endAt = $endAt->subDay();
        }

        $uid = trim($p['UID']['value'] ?? '') ?: md5(($p['SUMMARY']['value'] ?? '').$startAt->toIso8601String());
        if (isset($p['RECURRENCE-ID'])) {
            $uid .= '#'.$p['RECURRENCE-ID']['value'];
        }

        [$repeats, $until] = $this->rule($p['RRULE']['value'] ?? null, $startAt);

        return [
            'uid' => mb_substr($uid, 0, 255),
            'title' => mb_substr($this->text($p['SUMMARY']['value'] ?? '') ?: '(sonder naam)', 0, 255),
            'starts_on' => $startAt->toDateString(),
            'ends_on' => $endAt !== null && $endAt->toDateString() > $startAt->toDateString() ? $endAt->toDateString() : null,
            'start_time' => $allDay ? null : $startAt->format('H:i'),
            'end_time' => $allDay || $endAt === null || $endAt->toDateString() !== $startAt->toDateString() ? null : $endAt->format('H:i'),
            'location' => mb_substr($this->text($p['LOCATION']['value'] ?? ''), 0, 255) ?: null,
            'notes' => mb_substr($this->text($p['DESCRIPTION']['value'] ?? ''), 0, 5000) ?: null,
            'repeats' => $repeats,
            'repeat_until' => $until,
        ];
    }

    /**
     * @param  array{value: string, params: array<string, string>}  $prop
     * @return array{0: CarbonImmutable, 1: bool}|null
     */
    private function moment(array $prop): ?array
    {
        $value = trim($prop['value']);

        try {
            if (($prop['params']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
                return [CarbonImmutable::createFromFormat('!Ymd', substr($value, 0, 8), self::ZONE) ?: throw new \Exception, true];
            }

            if (str_ends_with($value, 'Z')) {
                $at = CarbonImmutable::createFromFormat('Ymd\THis\Z', $value, 'UTC');
            } else {
                $zone = $prop['params']['TZID'] ?? self::ZONE;
                try {
                    new \DateTimeZone($zone);
                } catch (\Throwable) {
                    // Outlook's "South Africa Standard Time" and the like: assume local time.
                    $zone = self::ZONE;
                }
                $at = CarbonImmutable::createFromFormat('Ymd\THis', $value, $zone);
            }
        } catch (\Throwable) {
            return null;
        }

        return $at ? [$at->setTimezone(self::ZONE), false] : null;
    }

    /** @return array{0: string, 1: string|null} [repeats, repeat_until] */
    private function rule(?string $rrule, CarbonImmutable $start): array
    {
        if ($rrule === null) {
            return ['none', null];
        }

        $rule = [];
        foreach (explode(';', strtoupper($rrule)) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $rule[$k] = $v;
        }

        $interval = (int) ($rule['INTERVAL'] ?? 1);
        $byDay = isset($rule['BYDAY']) ? explode(',', $rule['BYDAY']) : [];
        $startDay = strtoupper(substr($start->locale('en')->englishDayOfWeek, 0, 2));

        $repeats = match (true) {
            ($rule['FREQ'] ?? '') === 'WEEKLY' && $interval === 1 => 'weekly',
            ($rule['FREQ'] ?? '') === 'WEEKLY' && $interval === 2 => 'fortnightly',
            ($rule['FREQ'] ?? '') === 'MONTHLY' && $interval === 1 && $byDay === [] => 'monthly',
            ($rule['FREQ'] ?? '') === 'YEARLY' && $interval === 1 => 'yearly',
            default => 'none',
        };

        // "Every Monday and Wednesday" can't be one repeating entry here; keep just the first one.
        if ($repeats !== 'none' && $byDay !== [] && $byDay !== [$startDay]) {
            $repeats = 'none';
        }

        if ($repeats === 'none') {
            return ['none', null];
        }

        $until = null;
        if (isset($rule['UNTIL']) && preg_match('/^(\d{8})/', $rule['UNTIL'], $m)) {
            $until = CarbonImmutable::createFromFormat('!Ymd', $m[1], self::ZONE)?->toDateString();
        } elseif (isset($rule['COUNT']) && (int) $rule['COUNT'] > 0) {
            $n = (int) $rule['COUNT'] - 1;
            $last = match ($repeats) {
                'weekly' => $start->addWeeks($n),
                'fortnightly' => $start->addWeeks($n * 2),
                'monthly' => $start->addMonthsNoOverflow($n),
                default => $start->addYears($n),
            };
            $until = $last->toDateString();
        }

        return [$repeats, $until];
    }

    private function text(string $value): string
    {
        $value = str_replace(['\\n', '\\N', '\\,', '\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
    }
}
