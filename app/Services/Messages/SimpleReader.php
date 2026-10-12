<?php

namespace App\Services\Messages;

use Carbon\CarbonImmutable;

/**
 * Reads the obvious parts of an Afrikaans or English message without any outside service: a date
 * ("Saterdag", "more", "12 Okt", "12/10"), a time ("12:30", "3uur", "half een") and whether it sounds
 * like a family get-together. It is the fallback; the person checks everything before saving.
 */
final class SimpleReader
{
    private const DAYS = [
        'maandag' => 1, 'dinsdag' => 2, 'woensdag' => 3, 'donderdag' => 4, 'vrydag' => 5, 'saterdag' => 6, 'sondag' => 7,
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7,
    ];

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mrt' => 3, 'maa' => 3, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12,
    ];

    private const GATHERING_WORDS = [
        'ete', 'braai', 'kuier', 'almal', 'familie', 'potjie', 'middagete', 'aandete', 'ontbyt', 'partytjie',
        'kersfees', 'paasfees', 'lunch', 'dinner', 'party', 'everyone', 'family', 'kom eet', 'koffie',
    ];

    public function read(string $text, CarbonImmutable $today): Suggestion
    {
        $lower = mb_strtolower($text);

        [$start, $end] = $this->times($lower);

        return new Suggestion(
            title: $this->title($text),
            kind: $this->soundsLikeGathering($lower) ? 'gathering' : 'event',
            startsOn: $this->date($lower, $today)?->toDateString(),
            startTime: $start,
            endTime: $end,
        );
    }

    private function date(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        // 2026-10-18
        if (preg_match('/\b(20\d{2})-(\d{1,2})-(\d{1,2})\b/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 18 Okt / 18 Oktober 2026 / 18 October
        if (preg_match('/\b(\d{1,2})\s*(jan|feb|mrt|maa|mar|apr|mei|may|jun|jul|aug|sep|okt|oct|nov|des|dec)[a-z]*\.?(?:\s+(20\d{2}))?/u', $text, $m)) {
            return $this->dayMonth((int) $m[1], self::MONTHS[$m[2]], isset($m[3]) ? (int) $m[3] : null, $today);
        }

        // 18/10 or 18/10/2026 (day first, as in South Africa)
        if (preg_match('~\b(\d{1,2})/(\d{1,2})(?:/(20\d{2}))?\b~', $text, $m)) {
            return $this->dayMonth((int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : null, $today);
        }

        if (preg_match('/\b(oormôre|oormore|day after tomorrow)\b/u', $text)) {
            return $today->addDays(2);
        }
        if (preg_match('/\b(môre|more|tomorrow)\b/u', $text)) {
            return $today->addDay();
        }
        if (preg_match('/\b(vanaand|vandag|today|tonight)\b/u', $text)) {
            return $today;
        }

        // "Saterdag", "volgende Saterdag": the next one after today.
        foreach (self::DAYS as $name => $iso) {
            if (preg_match('/\b'.$name.'\b/u', $text)) {
                $days = ($iso - $today->dayOfWeekIso + 7) % 7 ?: 7;
                $next = preg_match('/\b(volgende|next)\s+(week\s+)?'.$name.'/u', $text) && $days < 7 ? $days + 7 : $days;

                return $today->addDays($next);
            }
        }

        return null;
    }

    private function dayMonth(int $day, int $month, ?int $year, CarbonImmutable $today): ?CarbonImmutable
    {
        $yearGiven = $year !== null;
        $year ??= $today->year;
        if (! checkdate($month, $day, $year)) {
            return null;
        }
        $date = CarbonImmutable::create($year, $month, $day);

        // A date without a year that has passed means next year ("5 Jan" said in December).
        return $date->lessThan($today) && ! $yearGiven ? $date->addYear() : $date;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function times(string $text): array
    {
        $found = [];

        // 12:30, 12h30, 12.30
        if (preg_match_all('/\b([01]?\d|2[0-3])[:h.]([0-5]\d)\b/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $t) {
                $found[] = sprintf('%02d:%02d', (int) $t[1], (int) $t[2]);
            }
        }

        // "half een" = 12:30; "half drie" = 14:30 (afternoon assumed for 1–7)
        $numbers = ['een' => 1, 'twee' => 2, 'drie' => 3, 'vier' => 4, 'vyf' => 5, 'ses' => 6, 'sewe' => 7, 'agt' => 8, 'nege' => 9, 'tien' => 10, 'elf' => 11, 'twaalf' => 12];
        if ($found === [] && preg_match('/\bhalf\s+('.implode('|', array_keys($numbers)).')\b/u', $text, $t)) {
            $found[] = sprintf('%02d:30', $this->afternoon($numbers[$t[1]] - 1 ?: 12));
        }

        // "3uur", "3pm", "6nm" — or "om 3", "at 6" without a suffix
        $hour = 0;
        $suffix = '';
        if ($found === [] && preg_match('/\b(\d{1,2})\s*(uur|pm|am|nm|vm)\b/u', $text, $t)) {
            $hour = (int) $t[1];
            $suffix = $t[2];
        } elseif ($found === [] && preg_match('/\b(?:om|at)\s+(\d{1,2})\b/u', $text, $t)) {
            $hour = (int) $t[1];
        }
        if ($found === [] && $hour >= 1 && $hour <= 23) {
            $hour = match ($suffix) {
                'pm', 'nm' => $hour < 12 ? $hour + 12 : $hour,
                'am', 'vm' => $hour === 12 ? 0 : $hour,
                default => $this->afternoon($hour),
            };
            $found[] = sprintf('%02d:00', $hour);
        }

        return [$found[0] ?? null, $found[1] ?? null];
    }

    /** Family plans at "3" or "half vier" are in the afternoon, not at night. */
    private function afternoon(int $hour): int
    {
        return $hour >= 1 && $hour <= 7 ? $hour + 12 : $hour;
    }

    private function soundsLikeGathering(string $text): bool
    {
        foreach (self::GATHERING_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** The first sentence, without greetings and emoji, as a short title. */
    private function title(string $text): string
    {
        $text = (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $text);
        $text = (string) preg_replace('/^\s*(hallo|hi|hey|haai|goeiemôre|goeiemore|naand)\b[\s,]*(almal|familie|ouens|julle)?[\s,!.]*/iu', '', $text);
        $first = trim((string) preg_split('/(?<=[.!?])\s+|\R/u', trim($text))[0]);
        $first = trim($first, " \t,.!?");

        if ($first === '') {
            return __('Nuwe plan');
        }

        return mb_strlen($first) > 60 ? rtrim(mb_substr($first, 0, 57)).'…' : mb_strtoupper(mb_substr($first, 0, 1)).mb_substr($first, 1);
    }
}
