# Tribe

A private, invite-only family organiser for our extended family (three households), in Afrikaans,
installed on iPhone and iPad as a web app (PWA). Not for sale.

Address: `tribe.bowlsbuddy.co.za`, on the Bowls Buddy Afrihost account and the same stack as
[Bowls Buddy](https://github.com/DawiePieterse/bowlsbuddy-app): Laravel 12, PHP 8.3, MariaDB. Deploying:
[docs/DEPLOY-AFRIHOST.md](docs/DEPLOY-AFRIHOST.md).

## What's in phase 1

- **Logins without passwords:** an emailed six-digit code, or an admin's single-use invite link shared on
  WhatsApp. Logins are remembered on each device.
- **Households:** each household has its own private things, and the whole family shares the rest. Every
  event, list and contact is either "Net ons huis" or "Hele familie"; `Concerns/HouseholdScoped` enforces it.
  Admins manage people, not content: they can't see another household's private things either.
- **Calendar:** month view and an agenda, colour-coded per household, filtered by household or person.
  Birthdays come from people's profiles; anniversaries repeat yearly; school holidays span days.
- **Gatherings:** RSVPs per household with headcounts, a who-brings-what list, and the children's allergies.
- **Lists:** shopping and task lists that keep working with no signal (ticks and new items wait and sync).
- **Grandchildren:** sizes, favourites, allergies, wish list and milestones.
- **Belangrike nommers:** a family directory of doctors, schools and emergency numbers.
- **Groter teks:** a larger-text setting per person.

Next (phase 2): recipes and meal planner, schedules, calendar subscriptions, find-a-date, "Stuur na Tribe"
(share a WhatsApp message to create an event), push notifications.

## Getting started

Requires PHP 8.2+ (intl, mbstring, pdo_mysql) and MariaDB 10.6+ / MySQL 8.

```bash
cp .env.example .env             # set DB_*; TRIBE_DEMO=true for a made-up demo family
composer install
php artisan key:generate
php artisan migrate --seed
php artisan tribe:invite oupa@example.com   # demo: prints a login link
php artisan serve
```

## Checks

```bash
composer check   # Pint, Larastan (level 6), Pest
```

Tests use the MariaDB database `tribe_test` (user `tribe` / `tribe`, see `phpunit.xml`).

## Notes

- `composer.json` / `composer.lock` match Bowls Buddy's dependency set, including Filament, which Tribe
  doesn't use yet. It can be dropped with `composer remove filament/filament` where Packagist is reachable.
- All text is Afrikaans, wrapped in `__()`, so another language can be added with one `lang/<locale>.json`.
- No family details are kept in this repository: the seeder only creates the first admin from `.env`.
