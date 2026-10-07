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

Everything below works in **cPanel > Terminal** (no SSH or South African IP needed).

1. **Code:** in the Terminal, clone the repository into `~/tribe`:
   `cd ~ && git clone https://github.com/DawiePieterse/tribe-app.git tribe`
   (A private repository needs a GitHub token or deploy key; the simplest is to make the clone over HTTPS
   with a fine-grained token that can only read this one repository.)
2. **Composer:** `cd ~/tribe && curl -sS https://getcomposer.org/installer | php` then
   `php composer.phar install --no-dev --optimize-autoloader`.
3. **Subdomain:** cPanel > Domains > Create A New Domain: `tribe.bowlsbuddy.co.za`, untick **Share document
   root**, document root `tribe/public`. Do this after step 1, so cPanel doesn't create the folder first.
4. **PHP:** cPanel > MultiPHP Manager: tick the subdomain, choose PHP 8.3, Apply (check it didn't jump back).
5. **Database:** cPanel > Database Wizard: database `tribe`, user `tribe`, **ALL PRIVILEGES**. Note the full
   names (with the `bowlsbg5n9w0_` prefix) and the password.
6. **Mailbox:** cPanel > Email Accounts: create `tribe@bowlsbuddy.co.za`. Its **Connect Devices** page shows
   the SMTP server and port for `.env`.
7. **Settings:** `cp .env.afrihost.example .env`, fill in the database, mail and `TRIBE_ADMIN_*` values
   (File Manager > Edit, or `nano .env`), then `php artisan key:generate`.
8. **Tables and the first admin:** `php artisan migrate --force && php artisan db:seed --force`.
9. **HTTPS:** wait until cPanel > SSL/TLS Status shows a real (not self-signed) certificate for the
   subdomain, then turn on **Force HTTPS Redirect** for it in cPanel > Domains. Logins need HTTPS.
10. **First login:** `php artisan tribe:invite <admin email>` prints a link. Open it on your iPhone in
    Safari, then add Tribe to the Home Screen. Add the other households and people in the app
    (Meer › Familie) and send them invites from there, on WhatsApp.

## Updates

```bash
cd ~/tribe
git pull
php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Backups

Afrihost keeps 14 days of files and databases, restored only by their support. Until Tribe has its own
download-backup button, download the database weekly from cPanel > Backup (or phpMyAdmin > Export).

## Troubleshooting

- **Login code never arrives:** check the mail settings in `.env`, and `storage/logs/laravel.log`. An admin
  can always send a new invite link instead (Meer › Familie › Uitnodig).
- **Server error:** read `~/tribe/storage/logs/laravel.log`.
- **Notifications on iPhone:** need iOS 16.4 or later and Tribe added to the Home Screen (planned).
