# Deploying Tribe to Afrihost

Tribe runs on the same Afrihost cPanel account as Bowls Buddy (Bronze Pro, `bowlsbuddy.co.za`), as the
subdomain `tribe.bowlsbuddy.co.za` with its own folder and database. Account details (server, cPanel user,
SSH, backups) are in Bowls Buddy's `docs/DEPLOY-AFRIHOST.md`. No passwords or keys are kept in this file.

| What | Value |
|---|---|
| Address | `https://tribe.bowlsbuddy.co.za` |
| Folder | `~/tribe` (code and `.env`), document root `~/tribe/public` |
| Database and user | `bowlsbg5n9w0_tribe` |
| PHP | 8.3 (ea-php83), set in MultiPHP Manager |
| Mail | mailbox `tribe@bowlsbuddy.co.za`, for login codes |

## First install

Composer isn't available in Afrihost's jailed Terminal, so Tribe ships as a zip with its packages
included, built by `scripts/build-afrihost.sh` (output: `build/afrihost/tribe.zip`). The Terminal is used
only for `php artisan` commands.

1. **Files:** cPanel > File Manager, in your home folder (not `public_html`): create the folder `tribe`,
   open it, Upload `tribe.zip`, then select it and Extract it there. Delete the zip afterwards.
2. **Subdomain:** cPanel > Domains > Create A New Domain: `tribe.bowlsbuddy.co.za`, untick **Share document
   root**, document root `tribe/public`.
3. **PHP:** cPanel > MultiPHP Manager: tick the subdomain, choose PHP 8.3 (ea-php83), Apply (check it
   didn't jump back).
4. **Database:** cPanel > Database Wizard: database `tribe`, user `tribe`, **ALL PRIVILEGES**. The full
   names become `bowlsbg5n9w0_tribe`. Keep the password.
5. **Mailbox:** cPanel > Email Accounts > Create: `tribe@bowlsbuddy.co.za`. Its **Connect Devices** page
   shows the outgoing (SMTP) server and port.
6. **Settings and tables:** cPanel > Terminal:

   ```bash
   cd ~/tribe
   php -v                              # must say 8.3; otherwise use /opt/cpanel/ea-php83/root/usr/bin/php
   cp .env.afrihost.example .env
   nano .env                           # DB_PASSWORD, MAIL_PASSWORD, TRIBE_ADMIN_NAME, TRIBE_ADMIN_EMAIL
   php artisan key:generate --force
   php artisan migrate --force
   php artisan db:seed --force         # the first household and its admin, from .env
   php artisan config:cache
   ```

   In nano: Ctrl+O, Enter to save, Ctrl+X to leave.
7. **HTTPS:** cPanel > SSL/TLS Status: wait for a real (not self-signed) certificate for the subdomain
   (Run AutoSSL if offered). Then cPanel > Domains: **Force HTTPS Redirect** on for the subdomain.
8. **Scheduled jobs:** cPanel > Cron Jobs, Once Per Minute:
   `cd ~/tribe && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
   This syncs subscribed calendars hourly.
9. **First login:** in the Terminal, `php artisan tribe:invite <admin email>` prints a link. Open it on your
   iPhone in Safari and add Tribe to the Home Screen. Add the other households and people in the app
   (Meer › Familie) and send them invites from there.
10. **Claude for Stuur na Tribe (optional):** an API key from console.anthropic.com in `.env` as
    `ANTHROPIC_API_KEY`, then `php artisan config:cache`. Without it Tribe uses its built-in reader.

## Updates

1. Build a new zip (`scripts/build-afrihost.sh`).
2. Upload it to `~/tribe` and Extract, replacing the old files. `.env` and `storage/` are kept (the zip
   has neither a `.env` nor any stored files).
3. In the Terminal: `cd ~/tribe && php artisan migrate --force && php artisan config:cache && php artisan view:clear`

## Backups

Afrihost keeps 14 days of files and databases, restored only by their support. Until Tribe has its own
download-backup button, download the database weekly from cPanel > Backup (or phpMyAdmin > Export).

## Troubleshooting

Problems met on the first install (13 Oct 2026), in the order they came up:

- **"Index of /" with `cgi-bin` and `php.ini`:** the subdomain's document root is cPanel's own new folder.
  Domains > Manage > set it to `tribe/public`.
- **Blank page, log says "No application encryption key has been specified":** `APP_KEY` is empty, or
  `config:cache` ran before `key:generate`. Run `php artisan key:generate --force && php artisan config:cache`.
- **500, log says "Table … sessions doesn't exist":** `php artisan migrate --force` hasn't run.
- **419 Page Expired:** the login page was opened before the app key changed. Reload it and try again.
- **500 when sending a code, log says "535 Incorrect authentication data":** the mailbox password in `.env`
  is wrong. Use letters and digits only and single quotes (`MAIL_PASSWORD='…'`); in double quotes `.env`
  treats `$` and `\` specially. Then `php artisan config:cache`.
- **Reading the log:** `grep -o 'production.ERROR: .\{0,300\}' storage/logs/laravel.log | tail -n 2`


- **Login code never arrives:** check the mail settings in `.env`, and `storage/logs/laravel.log`. An admin
  can always send a new invite link instead (Meer › Familie › Uitnodig).
- **Server error:** read `~/tribe/storage/logs/laravel.log`.
- **Notifications on iPhone:** need iOS 16.4 or later and Tribe added to the Home Screen (planned).
