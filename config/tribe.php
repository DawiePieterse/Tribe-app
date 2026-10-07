<?php

// First-install settings for `php artisan db:seed` (see DatabaseSeeder).

return [
    'household' => env('TRIBE_HOUSEHOLD', 'Oupa en Ouma'),
    'admin_name' => env('TRIBE_ADMIN_NAME', 'Oupa'),
    'admin_email' => env('TRIBE_ADMIN_EMAIL', ''),
    'demo' => (bool) env('TRIBE_DEMO', false),
];
