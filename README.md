# Tribe

A private, invite-only family organiser for our extended family (three households), in Afrikaans,
installable on iPhone and iPad as a PWA. Not for sale.

Planned address: `tribe.bowlsbuddy.co.za` on the existing Afrihost account, built on the same stack as
[Bowls Buddy](https://github.com/DawiePieterse/bowlsbuddy-app): Laravel 12 + Filament 4, PHP 8.3, MariaDB.

## Status

Skeleton only: the stock Laravel 12 app plus Bowls Buddy's `composer.json` / `composer.lock` (same
dependency set). Phase 1 (logins and households, calendar, gatherings, lists, grandchild profiles,
family directory) is next. See the Tribe spec for features and build order.

## Getting started

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
```
