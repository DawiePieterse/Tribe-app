<?php

namespace App\Services;

use App\Models\Household;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A day's forecast for a household's town from Open-Meteo (free, no key), kept for half an hour.
 * Each household sets its town under Familie; without one there is no weather.
 */
class Weather
{
    public const HOT = 28;

    public const COLD = 10;

    public const RAIN = 40;

    /**
     * Finds a town: its name as Open-Meteo knows it, and where it is.
     *
     * @return array{town: string, lat: string, lon: string}|null
     */
    public function find(string $town): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://geocoding-api.open-meteo.com/v1/search', [
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

    /** @return array{min: int, max: int, rain: int, wind: int, condition: string, flags: list<string>}|null */
    public function for(Household $household, CarbonImmutable $date): ?array
    {
        if (! $household->hasWeather()) {
            return null;
        }

        $day = $date->toDateString();

        return Cache::remember('weather.'.$household->lat.','.$household->lon.'.'.$day, now()->addMinutes(30), function () use ($household, $day): ?array {
            try {
                $response = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $household->lat,
                    'longitude' => $household->lon,
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode,windspeed_10m_max',
                    'timezone' => Dates::ZONE,
                    'start_date' => $day,
                    'end_date' => $day,
                ]);
                $d = $response->successful() ? $response->json('daily') : null;
                if (! is_array($d) || ! isset($d['temperature_2m_min'][0], $d['temperature_2m_max'][0])) {
                    return null;
                }

                $w = [
                    'min' => (int) round($d['temperature_2m_min'][0]),
                    'max' => (int) round($d['temperature_2m_max'][0]),
                    'rain' => (int) ($d['precipitation_probability_max'][0] ?? 0),
                    'wind' => (int) round($d['windspeed_10m_max'][0] ?? 0),
                    'condition' => self::condition($d['weathercode'][0] ?? null),
                ];
                $w['flags'] = self::flags($w);

                return $w;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * "12–24 °C · reën · reën 60%"
     *
     * @param  array{min: int, max: int, rain: int, wind: int, condition: string, flags: list<string>}  $w
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
     * @param  array{min: int, max: int, rain: int, wind: int, condition: string}  $w
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
