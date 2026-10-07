<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\Login;
use Illuminate\Console\Command;

/** For the first login on a new server (cPanel > Terminal): prints an invite link for an adult. */
class InviteCommand extends Command
{
    protected $signature = 'tribe:invite {email : The email address of an adult in the family}';

    protected $description = 'Print a single-use login link for a family member';

    public function handle(Login $login): int
    {
        $member = Member::query()->where('email', strtolower((string) $this->argument('email')))->first();

        if ($member === null || ! $member->canLogIn()) {
            $this->error('No adult with that email address.');

            return self::FAILURE;
        }

        $this->line($login->createInvite($member));

        return self::SUCCESS;
    }
}
