<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;

class ProviderFactory
{
    public function for(string $provider): CalendarProvider
    {
        return match ($provider) {
            CalendarConnection::GOOGLE => app(GoogleCalendar::class),
            CalendarConnection::OUTLOOK => app(OutlookCalendar::class),
            CalendarConnection::ICLOUD => app(ICloudCalendar::class),
            default => throw new \InvalidArgumentException("Unknown calendar provider {$provider}"),
        };
    }

    public function oauth(string $provider): OAuthProvider
    {
        $instance = $this->for($provider);

        return $instance instanceof OAuthProvider ? $instance : throw new \InvalidArgumentException("{$provider} doesn't sign in with OAuth");
    }
}
