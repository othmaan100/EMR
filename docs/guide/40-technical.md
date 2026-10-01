# Technical reference (IT officer)

## Platform

| Item | Detail |
|---|---|
| Application | Laravel 12 (PHP 8.2+), Bootstrap 5 interface built with Vite |
| Database | MySQL 8 / MariaDB 10.6+ (one database per hospital installation) |
| Web server | Apache (e.g. XAMPP) or Nginx, serving the `public` folder |
| PHP extensions | pdo_mysql, mbstring, openssl, fileinfo, gd, zip, xml, intl recommended |
| Libraries of note | spatie/laravel-permission (roles), openspout (Excel import/export), phpoffice/phpword (this guide) |

## Installation (summary)

1. Copy the application to the server; run `composer install --no-dev` and `npm ci && npm run build`.
2. Copy `.env.example` to `.env`; set `APP_URL`, database credentials, `APP_ENV=production`, `APP_DEBUG=false`; run `php artisan key:generate`.
3. Create the database and run `php artisan migrate --force` then `php artisan db:seed --force` (roles, permissions, catalogues).
4. Point the web server at `public/`, enable HTTPS, and open the site to run the setup wizard.
5. Set up the scheduled task (below) and off-site backups.

## Scheduled tasks

The Laravel scheduler must run **every minute**: on Linux `* * * * * cd /path/to/emr && php artisan schedule:run`; on Windows, a Task Scheduler task every minute running `php artisan schedule:run` in the application folder.

| Task | When |
|---|---|
| Scheduler heartbeat (System Health) | Every minute |
| Send queued SMS (`emr:send-sms`) | Every minute |
| Link imaging studies from the PACS (`emr:pacs-sync`) | Every 5 minutes |
| Daily bed charges (`emr:charge-beds`) | 00:05 |
| Nightly backup (`emr:backup`) | 01:30 |
| Appointment and immunization reminders (`emr:sms-reminders`) | 08:00 |

## Commands

| Command | Purpose |
|---|---|
| `php artisan emr:backup` | Back up the database and private files now |
| `php artisan emr:import` | List import types |
| `php artisan emr:import patients file.xlsx` | Check an import file (nothing saved) |
| `php artisan emr:import patients file.xlsx --commit [--update]` | Import it (for very large files) |
| `php artisan emr:user-guide` | Regenerate this guide as a Word document |
| `php artisan emr:import-icd10 file.csv` | Load or refresh the ICD-10 list |
| `php artisan emr:charge-beds` | Post missing bed charges now |
| `php artisan emr:send-sms` / `emr:sms-reminders` | Send queued SMS / queue reminders now |
| `php artisan emr:pacs-sync` | Link PACS studies now |

## Updating an installation

Always in this order: **(1)** `php artisan emr:backup`, **(2)** copy the new code, `composer install --no-dev`, `npm run build`, **(3)** `php artisan migrate --force`, **(4)** `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (adds new permissions; never removes custom ones), **(5)** `php artisan optimize:clear`. Check System Health afterwards.

## API endpoints (machine-to-machine)

| Endpoint | Used by | Authentication |
|---|---|---|
| `POST /api/v1/lab/results` | Lab analysers / middleware (HL7 v2 or JSON) | `Authorization: Bearer <analyser token>` |
| `POST /api/v1/payments/webhook/paystack` | Paystack | `x-paystack-signature` HMAC-SHA512 of the body |
| `POST /api/v1/payments/webhook/flutterwave` | Flutterwave | `verif-hash` header equals the configured secret hash |

**Lab results JSON example:** `{"sample_id": "LAB2026-000123", "results": [{"code": "WBC", "value": "6.1"}]}`. HL7 v2 messages are answered with an ACK (MSA|AA or AE); JSON with a status and message. Payment callbacks from the browser go to `/payments/callback` and are always re-verified with the gateway.

## Go-live checklist

- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS enabled, strong database password.
- Scheduler running (System Health shows it green).
- Backups running and copied off the server; a restore tested.
- Hospital settings, logo, clinics, wards, staff and roles, catalogues and prices set up (or imported).
- Integrations tested against the providers' test environments before switching to live keys.
- Staff trained; a pilot run in one department before full roll-out.

## Troubleshooting

| Problem | What to check |
|---|---|
| Nobody can sign in / blank page | Is MySQL running? Check `storage/logs/laravel.log`; run pending migrations |
| "Scheduler has never run" | The every-minute task is missing or failing |
| SMS not sent | Provider "Log only"? Key/credit with the provider? SMS log error text |
| Lab results not arriving | Integrations message log; token correct; codes mapped; sample barcode = lab order number |
| Images not linking | PACS test connection; accession number entered at the modality |
| Online payment not recorded | Integrations log (verification or signature errors); webhook URL set in the gateway |
| Prices not charged | The item has no price for that payer — check the Price List |
