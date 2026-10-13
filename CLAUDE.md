# Tribe: working notes for Claude

Private family organiser (three households) in Afrikaans, an iPhone/iPad PWA. Laravel 12, PHP 8.3, MariaDB,
deployed on Afrihost cPanel (`docs/DEPLOY-AFRIHOST.md`). The spec is the "Tribe: Family App Spec" doc.

## Layout

| Where | What |
|---|---|
| `app/Models` | `Household`, `Member` (everyone; adults with email log in, children are profiles), `Event` (kinds: event, gathering, anniversary, school; `repeats` none/weekly/fortnightly/monthly/yearly + `repeat_until`; `feed_id` = copied from a subscribed calendar, read-only), `GatheringResponse`, `GatheringItem`, `TodoList` (table `lists`), `ListItem`, `Contact`, `Milestone`, `LoginCode`, `Invite`, `Recipe`, `Meal`, `CalendarFeed`, `DatePoll` / `DatePollOption` / `DatePollVote`, `InboxItem`, `CalendarConnection` / `EventLink`, `Grocery` |
| `app/Models/Concerns/HouseholdScoped.php` | The privacy rule: `household_id` null = whole family, set = only that household. Use `visibleTo()` in queries and `ensureVisible()` in controllers (answers 404). |
| `app/Services/Agenda.php` | Merges events, yearly anniversaries and birthdays into `Support\Occurrence` lines. |
| `app/Services/Login.php` | Emailed codes (HMAC-hashed, 15 min, 5 tries) and single-use invite links; logins are remembered. |
| `app/Services/CalendarSync.php`, `app/Support/Ics/*` | Subscribed calendars: fetch, parse (`IcsParser`), upsert by UID; `tribe:sync-calendars` runs hourly. `IcsWriter` + `IcsController` publish each member's own feed (`/kalender/<token>.ics`). |
| `app/Services/Sync/*` | Two-way sync with a member's own calendar: `CalendarConnection` (provider google/outlook/icloud, encrypted credentials), `EventLink` (remote id + version, local hash), `TwoWaySync` engine, providers `GoogleCalendar`, `OutlookCalendar` (Graph), `ICloudCalendar` (CalDAV, Apple hosts only). `SyncSoon` syncs after a Tribe event changes; `tribe:sync-connections` every 10 min. |
| `app/Services/Food/*` | Kosidees: `Chef` uses `ClaudeChef` (with `ANTHROPIC_API_KEY`) or `BuiltInChef` (`cookbook.php`, kid-friendly SA meals); `Ingredients` maps Afrikaans/English/till-slip words to plain names; `Portions` scales ingredient lines to `Household::peopleEating()`; `ReceiptReader` reads slips/Sixty60 orders (Claude vision) or pasted text into `Grocery` rows (per household, 21 days). `Support\Claude` makes one forced tool call. |
| `app/Services/RecipeImporter.php` | Reads schema.org/Recipe JSON-LD from a page. |
| `app/Services/Messages/*` | "Stuur na Tribe": `MessageReader` uses `ClaudeReader` (forced tool call) when `ANTHROPIC_API_KEY` is set, else `SimpleReader`; both return a `Suggestion`. The Shortcut posts to `/api/stuur` with a per-member Bearer key (stored hashed). |
| `app/Support/SafeUrl.php` | Every server-side fetch of a user-supplied URL goes through it (public http(s) hosts only, redirects re-checked). |
| `resources/views` | Blade, mobile-first, `<x-layout>`; icons in `partials/icon.blade.php`. |
| `public/css/app.css`, `public/js/*.js`, `public/sw.js` | No build step. The CSP forbids inline scripts: use data attributes handled in `app.js`. |
| `public/js/lists.js` | Offline queue for list ticks and new items (localStorage, replayed when online). |

## Conventions

- All user-facing text is Afrikaans inside `__()`; routes are Afrikaans paths (`/kalender`, `/lyste`).
- Household colours carry the design on an Apple-like base: grouped white cards on `#F5F5F7` (black in dark mode),
  floating glass tab bar, graphite buttons, sapphire tint (`--action`) for the app's own controls; SF Pro text
  (`--sans`) and New York (`--serif`, `ui-serif`) for titles and dates, both system fonts; 17px body.
- Models declare `$attributes` defaults; lazy loading throws outside production, so eager-load.
- Never put real family details in seeders or tests; `DemoSeeder` is a made-up family.
- Use `Support\Dates::shortDay()` for short weekday names (Carbon's Afrikaans ones are wrong), and
  `startOfWeek(CarbonInterface::MONDAY)` (the af locale would start on Sunday).
- Record route parameters are constrained to digits in `routes/web.php`; add new ones to that list.
- Never `pkill -f` a pattern that also appears in your own command line (it kills the shell).

## Checks (run before committing)

```bash
composer check   # vendor/bin/pint --test, phpstan level 6, pest
```

## Session setup (Claude Code on the web)

`.claude/hooks/session-start.sh` installs MariaDB, creates `tribe` / `tribe_test` (user `tribe`/`tribe`),
installs Composer packages from GitHub sources (Packagist may be blocked by the egress proxy; the lock file
makes that unnecessary), creates `.env` and migrates. Chromium for screenshots is at `/opt/pw-browsers/chromium`.
