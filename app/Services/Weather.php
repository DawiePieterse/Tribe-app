<?php

namespace App\Services;

use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A day's forecast for the family's town from Open-Meteo (free, no key), kept for half an hour.
 * Off unless TRIBE_LAT and TRIBE_LON are set.
 */
class Weather
{
    public const HOT = 28;

    public const COLD = 10;

    public const RAIN = 40;

    public static function enabled(): bool
    {
        return is_numeric(config('services.weather.lat')) && is_numeric(config('services.weather.lon'));
    }

    /** @return array{min: int, max: int, rain: int, wind: int, condition: string, flags: list<string>}|null */
    public function on(CarbonImmutable $date): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $day = $date->toDateString();

        return Cache::remember('weather.'.$day, now()->addMinutes(30), function () use ($day): ?array {
            try {
                $response = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => config('services.weather.lat'),
                    'longitude' => config('services.weather.lon'),
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
