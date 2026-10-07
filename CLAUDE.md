# Tribe: working notes for Claude

Private family organiser (three households) in Afrikaans, an iPhone/iPad PWA. Laravel 12, PHP 8.3, MariaDB,
deployed on Afrihost cPanel (`docs/DEPLOY-AFRIHOST.md`). The spec is the "Tribe: Family App Spec" doc.

## Layout

| Where | What |
|---|---|
| `app/Models` | `Household`, `Member` (everyone; adults with email log in, children are profiles), `Event` (kinds: event, gathering, anniversary, school), `GatheringResponse`, `GatheringItem`, `TodoList` (table `lists`), `ListItem`, `Contact`, `Milestone`, `LoginCode`, `Invite` |
| `app/Models/Concerns/HouseholdScoped.php` | The privacy rule: `household_id` null = whole family, set = only that household. Use `visibleTo()` in queries and `ensureVisible()` in controllers (answers 404). |
| `app/Services/Agenda.php` | Merges events, yearly anniversaries and birthdays into `Support\Occurrence` lines. |
| `app/Services/Login.php` | Emailed codes (HMAC-hashed, 15 min, 5 tries) and single-use invite links; logins are remembered. |
| `resources/views` | Blade, mobile-first, `<x-layout>`; icons in `partials/icon.blade.php`. |
| `public/css/app.css`, `public/js/*.js`, `public/sw.js` | No build step. The CSP forbids inline scripts: use data attributes handled in `app.js`. |
| `public/js/lists.js` | Offline queue for list ticks and new items (localStorage, replayed when online). |

## Conventions

- All user-facing text is Afrikaans inside `__()`; routes are Afrikaans paths (`/kalender`, `/lyste`).
- Household colours carry the design; deep blue for the app's own controls; serif headings; 18px body.
- Models declare `$attributes` defaults; lazy loading throws outside production, so eager-load.
- Never put real family details in seeders or tests; `DemoSeeder` is a made-up family.

## Checks (run before committing)

```bash
composer check   # vendor/bin/pint --test, phpstan level 6, pest
```

## Session setup (Claude Code on the web)

`.claude/hooks/session-start.sh` installs MariaDB, creates `tribe` / `tribe_test` (user `tribe`/`tribe`),
installs Composer packages from GitHub sources (Packagist may be blocked by the egress proxy; the lock file
makes that unnecessary), creates `.env` and migrates. Chromium for screenshots is at `/opt/pw-browsers/chromium`.
