<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\Member;
use App\Models\TodoList;
use Illuminate\Database\Seeder;

/**
 * First install: one household and its admin, taken from TRIBE_* in .env. Everyone else is added in
 * the app (Meer › Familie), so no family details live in this repository. Run `php artisan
 * tribe:invite <email>` afterwards for the admin's first login link.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local') && config('tribe.demo')) {
            $this->call(DemoSeeder::class);

            return;
        }

        $email = (string) config('tribe.admin_email');

        if ($email === '' || Member::query()->where('email', strtolower($email))->exists()) {
            $this->command->warn('Set TRIBE_ADMIN_EMAIL in .env (or the admin already exists).');

            return;
        }

        $household = Household::query()->create([
            'name' => (string) config('tribe.household'),
            'colour' => Household::COLOURS[0],
        ]);

        TodoList::query()->create(['household_id' => $household->id, 'name' => 'Inkopies', 'kind' => TodoList::SHOPPING]);

        Member::query()->create([
            'household_id' => $household->id,
            'name' => (string) config('tribe.admin_name'),
            'kind' => Member::ADULT,
            'email' => $email,
            'is_admin' => true,
        ]);

        $this->command->info("Admin created. Now run: php artisan tribe:invite {$email}");
    }
}
