<?php

namespace App\Services\Food;

/**
 * Scales an ingredient line ("500 g maalvleis", "1½ koppies rys", "8 eiers") from the number of people
 * a recipe is written for to the number at the table. Weights and volumes round to tidy amounts;
 * counts round to halves; the next word is made plural or singular when the count crosses one.
 */
final class Portions
{
    /** Units measured in grams or millilitres, rounded to tidy amounts. */
    private const MEASURED = ['g', 'kg', 'ml', 'l', 'liter', 'litre'];

    /** Afrikaans plurals for the words that usually follow a count in a recipe. */
    private const PLURALS = [
        'koppie' => 'koppies', 'blikkie' => 'blikkies', 'pakkie' => 'pakkies', 'sny' => 'snye', 'eier' => 'eiers',
        'ui' => 'uie', 'aartappel' => 'aartappels', 'patat' => 'patats', 'wortel' => 'wortels', 'tamatie' => 'tamaties',
        'wrap' => 'wraps', 'rolletjie' => 'rolletjies', 'rol' => 'rolle', 'hoenderborsie' => 'hoenderborsies',
        'hoenderboudjie' => 'hoenderboudjies', 'worsie' => 'worsies', 'filet' => 'filette', 'stokvisfilet' => 'stokvisfilette',
        'pita' => 'pitas', 'botterskorsie' => 'botterskorsies', 'suurlemoen' => 'suurlemoene', 'taco-dop' => 'taco-doppe',
    ];

    /** Things that can't be halved: never fewer than one, always a whole number. */
    private const WHOLE = ['eier', 'eiers', 'egg', 'eggs', 'wrap', 'wraps', 'pita', 'pitas', 'sny', 'snye', 'rolletjie', 'rolletjies', 'worsie', 'worsies', 'hoenderboudjie', 'hoenderboudjies'];

    private const FRACTIONS = ['¼' => 0.25, '½' => 0.5, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3];

    /**
     * @param  list<array{line: string, have: bool}>  $ingredients
     * @return list<array{line: string, have: bool}>
     */
    public static function scaleAll(array $ingredients, int $from, int $to): array
    {
        return array_map(fn ($i) => ['line' => self::scale($i['line'], $from, $to), 'have' => $i['have']], $ingredients);
    }

    public static function scale(string $line, int $from, int $to): string
    {
        if ($from === $to || $from < 1 || $to < 1) {
            return $line;
        }

        // "1½", "2.5", "½", "500" at the start, then the rest.
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)?([¼½¾⅓⅔])?(\s*)(.*)$/su', $line, $m) || ($m[1] === '' && $m[2] === '')) {
            return $line;
        }

        $amount = (float) str_replace(',', '.', $m[1]) + (self::FRACTIONS[$m[2]] ?? 0);
        $rest = $m[4];
        $unit = strtolower((string) preg_replace('/^(\S+).*/su', '$1', $rest));
        $scaled = $amount * $to / $from;

        $measured = in_array($unit, self::MEASURED, true);
        $scaled = match (true) {
            $measured => self::tidyMeasure($scaled, $unit),
            in_array(rtrim($unit, ',.;:'), self::WHOLE, true) => max(1, round($scaled)),
            $scaled < 1 => max(0.25, round($scaled * 4) / 4),
            default => round($scaled * 2) / 2,
        };

        // A count makes the next word plural or singular; grams and millilitres don't.
        return self::number($scaled).' '.ltrim($measured ? $rest : self::agree($rest, $amount, $scaled));
    }

    private static function tidyMeasure(float $value, string $unit): float
    {
        if (in_array($unit, ['kg', 'l', 'liter', 'litre'], true)) {
            return round($value * 4) / 4;
        }

        return match (true) {
            $value >= 500 => round($value / 50) * 50,
            $value >= 100 => round($value / 25) * 25,
            $value >= 20 => round($value / 5) * 5,
            default => round($value),
        };
    }

    /** 2 → "2", 2.5 → "2½", 0.25 → "¼", 1.33 → "1⅓" */
    private static function number(float $value): string
    {
        $whole = (int) floor($value + 1e-9);
        $part = $value - $whole;

        foreach (self::FRACTIONS as $glyph => $fraction) {
            if (abs($part - $fraction) < 0.05) {
                return ($whole > 0 ? $whole : '').$glyph;
            }
        }

        return $part < 0.05 ? (string) $whole : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** "1 koppie" becoming 2 reads "2 koppies"; "2 eiers" becoming 1 (or ½) reads "1 eier". */
    private static function agree(string $rest, float $was, float $now): string
    {
        $wasOne = $was <= 1.01;
        $nowOne = $now <= 1.01;
        if ($wasOne === $nowOne) {
            return $rest;
        }

        $map = $wasOne ? self::PLURALS : array_flip(self::PLURALS);

        return (string) preg_replace_callback('/^(\s*)(\S+)/u', function (array $m) use ($map): string {
            $word = $m[2];
            $trail = (string) preg_replace('/^[\p{L}\-]+/u', '', $word);
            $core = mb_substr($word, 0, mb_strlen($word) - mb_strlen($trail));
            $lower = mb_strtolower($core);

            return $m[1].(isset($map[$lower]) ? $map[$lower].$trail : $word);
        }, $rest, 1);
    }
}
