<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Makes the key pair that signs Tribe's push notifications; put the two lines in .env once. */
class VapidKeysCommand extends Command
{
    protected $signature = 'tribe:vapid-keys';

    protected $description = 'Generate the VAPID keys for push notifications (add them to .env)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->info('Add these to .env, then run: php artisan config:cache');

        return self::SUCCESS;
    }
}
