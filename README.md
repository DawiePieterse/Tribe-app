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

## What's in phase 2

- **Repeating entries:** weekly, every two weeks, monthly or yearly, until a date (sport, lessons, church).
- **Recipes:** typed in, or read from a link (the schema.org recipe data most recipe sites publish);
  ingredients go to a shopping list with one tap, after unticking what's already in the cupboard.
- **Weekmenu:** each household's week of meals; "Inkopies vir die week" gathers the recipes' ingredients.
- **Calendars:** everyone's own private link puts Tribe in the iPhone Calendar app (read-only, hourly);
  and Tribe subscribes to school, club or holiday calendars by `.ics` / `webcal://` link, synced hourly.
- **Vind ’n datum:** propose dates, each household marks yes / maybe / no, Tribe shows the date that suits
  everyone, and the chosen date becomes a gathering.
- **Stuur na Tribe:** paste a message from the family WhatsApp group, or share it from WhatsApp with an
  Apple Shortcut; Tribe reads the plan (with Claude if `ANTHROPIC_API_KEY` is set, otherwise a built-in
  Afrikaans/English reader) and it waits on the home screen to be checked and saved.

## What's in phase 3 so far

- **A new look:** Apple-style grouped cards, a floating glass tab bar, SF Pro and New York (the iPhone's own
  fonts), graphite and sapphire, with a true-black dark mode.
- **Two-way calendar sync:** each person connects their own Google, Outlook or iCloud calendar (Meer ›
  Kalenders). New and changed events go both ways; the later change wins when both sides changed.
  Deleting in Tribe deletes the copy; deleting a Tribe event from your own calendar only removes it for
  you. Synced every ten minutes and straight after a change in Tribe (`tribe:sync-connections`).

- **Wat maak ons vanaand?** (Lyste › Idees): type two or three things you have, choose quick (up to 20 min),
  medium (20–40) or more time (40+), and get child-friendly meals with Checkers/Woolworths ingredients;
  put one on the week menu, save it as a recipe, or send what's missing to a shopping list or the share
  sheet. Or tell Tribe what you bought: a photo of the till slip, a Sixty60 order screenshot or VAT invoice
  PDF (read by Claude), or pasted text; ideas then come from what's in the cupboard. Without
  `ANTHROPIC_API_KEY`, ideas come from Tribe's own cookbook and only pasted text can be read.
  Sixty60 has no public API or list import, so the list is shared as text.

Still to come: chores and pocket money, document vault, push notifications
(they need the `minishlink/web-push` package, which couldn't be installed while Packagist was blocked here).

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
