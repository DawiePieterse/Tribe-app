<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Outside production, fail on lazy loading (one query per row) and on attributes that aren't fillable.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale((string) config('app.locale'));

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Codes by email: a few requests per address, and a few guesses per minute.
        RateLimiter::for('login-send', fn (Request $request) => [
            Limit::perMinute(3)->by('send|'.Str::lower((string) $request->input('email'))),
            Limit::perMinute(10)->by('send-ip|'.$request->ip()),
        ]);
        RateLimiter::for('login-verify', fn (Request $request) => Limit::perMinute(10)->by('verify|'.$request->ip()));
    }
}
