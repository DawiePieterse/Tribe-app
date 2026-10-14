<?php

namespace App\Services;

use App\Models\Household;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The forecast for a household's town from Open-Meteo (free, no key): one request covers today and
 * the next two days, kept for two hours; a failed request is remembered for ten minutes so a slow
 * Open-Meteo never holds up the home page. Each household sets its town under Familie.
 *
 * @phpstan-type Forecast array{min: int, max: int, rain: int, condition: string, flags: list<string>}
 */
class Weather
{
    private const HOT = 28;

    private const COLD = 10;

    private const RAIN = 40;

    private const DAYS = 3;

    /**
     * Finds a town: its name as Open-Meteo knows it, and where it is.
     *
     * @return array{town: string, lat: string, lon: string}|null
     */
    public function find(string $town): ?array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $town, 'count' => 5, 'language' => 'af', 'format' => 'json',
            ]);
            $results = $response->successful() ? (array) $response->json('results', []) : [];
        } catch (\Throwable) {
            return null;
        }

        // South Africa first, then whatever matches best.
        usort($results, fn ($a, $b) => (($b['country_code'] ?? '') === 'ZA') <=> (($a['country_code'] ?? '') === 'ZA'));
        $hit = $results[0] ?? null;
        if (! is_array($hit) || ! isset($hit['latitude'], $hit['longitude'])) {
            return null;
        }

        return [
            'town' => mb_substr((string) ($hit['name'] ?? $town).(isset($hit['admin1']) ? ', '.$hit['admin1'] : ''), 0, 100),
            'lat' => number_format((float) $hit['latitude'], 5, '.', ''),
            'lon' => number_format((float) $hit['longitude'], 5, '.', ''),
        ];
    }

    /** @return Forecast|null */
    public function for(Household $household, CarbonImmutable $date): ?array
    {
        if (! $household->hasWeather()) {
            return null;
        }

        return $this->days($household)[$date->toDateString()] ?? null;
    }

    /**
     * Today and the next two days, by date.
     *
     * @return array<string, Forecast>
     */
    private function days(Household $household): array
    {
        $key = 'weather.'.$household->lat.','.$household->lon.'.'.CarbonImmutable::today()->toDateString();
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $days = $this->fetch($household);
        // An empty list (Open-Meteo down) is kept for a short while only.
        Cache::put($key, $days, $days === [] ? now()->addMinutes(10) : now()->addHours(2));

        return $days;
    }

    /** @return array<string, Forecast> */
    private function fetch(Household $household): array
    {
        $today = CarbonImmutable::today();

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $household->lat,
                'longitude' => $household->lon,
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode',
                'timezone' => Dates::ZONE,
                'start_date' => $today->toDateString(),
                'end_date' => $today->addDays(self::DAYS - 1)->toDateString(),
            ]);
        } catch (\Throwable) {
            return [];
        }

        $d = $response->successful() ? $response->json('daily') : null;
        if (! is_array($d) || ! is_array($d['time'] ?? null)) {
            return [];
        }

        $days = [];
        foreach ($d['time'] as $i => $day) {
            if (! isset($d['temperature_2m_min'][$i], $d['temperature_2m_max'][$i])) {
                continue;
            }
            $w = [
                'min' => (int) round($d['temperature_2m_min'][$i]),
                'max' => (int) round($d['temperature_2m_max'][$i]),
                'rain' => (int) ($d['precipitation_probability_max'][$i] ?? 0),
                'condition' => self::condition($d['weathercode'][$i] ?? null),
            ];
            $days[(string) $day] = $w + ['flags' => self::flags($w)];
        }

        return $days;
    }

    /**
     * "12–24 °C · reën · reën 60%"
     *
     * @param  Forecast  $w
     */
    public static function line(array $w): string
    {
        $line = $w['min'].'–'.$w['max'].' °C · '.$w['condition'];
        if ($w['rain'] >= 20) {
            $line .= ' · '.__('reën :p%', ['p' => $w['rain']]);
        }

        return $line;
    }

    /**
     * The line with what to bring: "9–31 °C · reën · reën 65% · Reën moontlik: reënjas"
     *
     * @param  Forecast  $w
     */
    public static function summary(array $w): string
    {
        return implode(' · ', [self::line($w), ...$w['flags']]);
    }

    /**
     * @param  array{min: int, max: int, rain: int, condition: string}  $w
     * @return list<string>
     */
    private static function flags(array $w): array
    {
        $flags = [];
        if ($w['max'] >= self::HOT) {
            $flags[] = __('Baie warm: waterbottel en hoed');
        }
        if ($w['min'] <= self::COLD) {
            $flags[] = __('Koud: trek warm aan');
        }
        if ($w['rain'] >= self::RAIN) {
            $flags[] = __('Reën moontlik: reënjas');
        }

        return $flags;
    }

    private static function condition(mixed $code): string
    {
        $code = is_numeric($code) ? (int) $code : null;

        return match (true) {
            $code === null => __('onbekend'),
            $code === 0 => __('helder'),
            $code <= 2 => __('gedeeltelik bewolk'),
            $code === 3 => __('bewolk'),
            $code <= 48 => __('mis'),
            $code <= 55 => __('motreën'),
            $code <= 65 => __('reën'),
            $code <= 75 => __('sneeu'),
            $code <= 82 => __('reënbuie'),
            default => __('donderbuie'),
        };
    }
}
