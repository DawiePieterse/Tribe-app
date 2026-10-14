<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // "Stuur na Tribe" reads dates out of chat messages with Claude when a key is set; without one a
    // simpler built-in reader is used.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    // Two-way sync with people's own calendars (Meer › Kalenders). Each needs an app registered with
    // Google or Microsoft; see docs/DEPLOY-AFRIHOST.md. iCloud needs nothing here.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'outlook' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    // Where a shopping-list item's "Checkers" link goes (%s is the search words). Change it in .env if
    // the site's search address changes.
    'checkers' => [
        'search' => env('CHECKERS_SEARCH_URL', 'https://www.checkers.co.za/search?Search=%s'),
    ],

    // The shops a shopping-list item can be looked up at, in button order: name and colour of the button,
    // and the search address (%s is the search words). An empty address leaves that shop's button out.
    // A logo saved as public/icons/shops/<key>.png (or .svg) is shown on the button instead of the name.
    'shops' => [
        ['key' => 'checkers', 'name' => 'Checkers', 'colour' => '#00A19A', 'search' => env('CHECKERS_SEARCH_URL', 'https://www.checkers.co.za/search?Search=%s')],
        ['key' => 'woolworths', 'name' => 'Woolies', 'label' => 'Woolworths', 'colour' => '#000000', 'search' => env('WOOLWORTHS_SEARCH_URL', 'https://www.woolworths.co.za/browse?searchterm=%s&fr=1')],
    ],

    // Push notifications (Web Push). Make the keys once with `php artisan tribe:vapid-keys`.
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
