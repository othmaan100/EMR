# Hospital EMR

A generic Electronic Medical Records / Hospital Management System built on Laravel 12.
Each hospital runs its own installation and database; all branding (name, logo, address,
currency, colours) is configured through a first-run **Setup Wizard** — no code changes needed.

## Requirements

- PHP 8.2+ with `pdo_mysql, mbstring, openssl, fileinfo, gd, curl, intl, zip`
- MySQL 5.7+ / MariaDB 10.3+
- Composer 2; Node 18+ (only to rebuild frontend assets)

## Installing for a new hospital

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# edit .env: APP_URL and DB_DATABASE / DB_USERNAME / DB_PASSWORD
npm install && npm run build
```

Create the empty database, then open the app in a browser. The Setup Wizard will:

1. **System Check** – verify PHP/extensions/permissions/DB and create the tables
2. **Hospital Profile** – name, short name, facility type, licence no., motto, logo
3. **Contact & Address** – address, city, state, country, phones, email, website
4. **Preferences** – currency, time zone, date format, patient number prefix, brand colour
5. **Administrator** – the first Super Admin account

After completion `storage/app/installed.lock` is written and the wizard is locked.
To re-run setup on a fresh database, delete that file.

### XAMPP (local)

Place the project in `htdocs/EMR` and browse to `http://localhost/EMR/public`.
For production, point an Apache virtual host's `DocumentRoot` at the `public/` folder.

## Development

```bash
php artisan test      # feature tests (SQLite in-memory)
npm run dev           # Vite dev server with hot reload
```

## Modules

| # | Module | Status |
|---|--------|--------|
| 1 | Foundation, Setup Wizard, Auth, Roles, Audit Log | ✅ Done |
| 2 | Administration (staff, departments, roles, profile) | ✅ Done |
| 3 | Patient Registration (+ insurance/HMO providers, patient card) | ✅ Done |
| 4 | Appointments, Check-in & Clinic Queue (+ clinics) | ✅ Done |
| 5 | Nursing / Triage (vitals, NEWS2, trend charts, nursing notes) | ✅ Done |
| 6 | Consultation (notes, ICD-10 diagnoses, lab/imaging/Rx orders, catalogues) | ✅ Done |
| 7 | Laboratory (collection, results + ranges, 2-person verification, reports) | ✅ Done |
| 8 | Radiology (schedule, perform, images, templated reports, sign-off) | ✅ Done |
| 9 | Pharmacy & Inventory (dispensing, FEFO batches, stock ledger, receiving, alerts) | ✅ Done |
| 10 | Billing & Payments (price lists, auto-charges, cashier, receipts, discounts, claims) | ✅ Done |
| 11 | Inpatients / Wards (bed board, admit/transfer/discharge, drug chart, bed charges, deposits) | ✅ Done |
| 12 | Reports & Analytics (12 reports, charts, CSV/Excel export, print) | ✅ Done |
| 13 | Go-live hardening (idle timeout, lockout, headers, backups, system health) | ✅ Done |
| 14 | Maternity & child health (ANC, partograph, delivery + baby registration, postnatal, immunization) | ✅ Done |
| 15 | Theatre & surgery (booking, pre-op, WHO checklist, anaesthesia record, op notes, charges) | ✅ Done |
| 16 | SMS (Termii / Africa's Talking / Twilio: reminders, results ready, immunizations) & pharmacy pay-first | ✅ Done |
| 17 | HMO / NHIA claims (PA codes, claim batches, claim forms, CSV export, remittance, rejections, ageing) | ✅ Done |
| 18 | Patient portal (activation codes, results, appointments requests, bills & receipts, children's immunizations) | ✅ Done |
| 19 | Specialty clinics (dental chart + treatment plans, eye exams + spectacle Rx, physiotherapy courses + pain tracking) | ✅ Done |
| 20 | Stores & procurement (general store, requisitions, purchase orders for store items & drugs, deliveries, supplier invoices) | ✅ Done |
| 21 | Data import (18 legacy record types, .xlsx/.csv templates, validate-then-import, error reports, CLI) | ✅ Done |
| 22 | Integrations: lab analysers (HL7 v2/JSON), PACS (DICOMweb), Paystack/Flutterwave, NHIA/HMO e-claims, NIN lookup | ✅ Done |

**User & Administrator Guide (Word):** `docs/EMR-User-Guide.docx`, generated from `docs/guide/*.md` with `php artisan emr:user-guide`.

## Key conventions

- `setting('key')` reads hospital settings (cached); `money()` and `format_date()` use them.
- Roles & permissions are defined in `config/emr.php`; re-sync with
  `php artisan db:seed --class=RolesAndPermissionsSeeder`. "Super Admin" bypasses all checks.
- Add `use Auditable;` to a model to log its create/update/delete in the audit trail.
- Uploads go to `public/uploads` (no `storage:link` symlink needed).

## Clinical catalogues

Starter lists of lab tests, imaging procedures, drugs and ~220 common ICD-10 codes are
seeded on install and can be edited under **Administration → Clinical Catalogues**.
To load a full ICD-10 code set (CSV with `code,description` rows):

```bash
php artisan emr:import-icd10 path/to/icd10.csv          # add / update codes
php artisan emr:import-icd10 path/to/icd10.csv --fresh  # replace the whole list
```

## Scheduled tasks

Daily inpatient bed charges are posted by `php artisan emr:charge-beds` at 00:05. Queued SMS are sent every minute
(`emr:send-sms`) and appointment / immunization reminders are queued at 08:00 (`emr:sms-reminders`).
The Laravel scheduler must run every minute:

- **Linux:** `* * * * * cd /path/to/emr && php artisan schedule:run >> /dev/null 2>&1`
- **Windows (XAMPP):** Task Scheduler → new task, trigger every 1 minute, action
  `C:\xampp\php\php.exe` with arguments `artisan schedule:run` and "Start in" set to the project folder.

Bed charges are also brought up to date on transfer and discharge, and via the
"Update bed charges" button on the inpatient chart.

## Go-live checklist for a new hospital

After the setup wizard, an administrator should:

1. **Staff & roles** – create staff accounts (Administration → Staff); review Roles & Permissions.
2. **Departments & clinics** – add departments, then clinics (queue codes such as `GOPD`) and whether each needs nurse triage.
3. **Wards & beds** – add an *Accommodation* service per ward type (Catalogues → Services), then wards and beds.
4. **Insurance / HMOs** – add payers, their coverage % and whether each needs a pre-authorisation (PA) code on claims.
5. **Catalogues** – review lab tests (and their reference ranges), imaging templates and the drug formulary.
6. **Prices** – Finance → Price List: set default prices (registration, consultation, tests, drugs, beds) and any insurer-specific prices. Unpriced items are not charged.
7. **Stock** – receive opening drug stock (Drug Inventory → Receive stock) and set reorder levels; add general store items (General Store → Items) and receive their opening balances.
8. **Preferences** – Hospital Settings: pay-before-service, pharmacy pay-first, patient portal (patients sign in at `/portal`), lab self-verification; SMS provider, keys and templates (SMS messaging tab — send a test from Administration → SMS Messages).
9. **Scheduler** – set up the every-minute scheduled task (see *Scheduled tasks*).
10. **Backups** – schedule regular MySQL backups (e.g. `mysqldump`) and copy `storage/app/private` (patient photos, imaging files) and `public/uploads`.

## Importing data from a previous system

Administration → **Data Import** lists every record type in the order to import them (departments → insurers → clinics & wards → suppliers → staff → patients → catalogues → prices → opening stock, balances, immunizations and appointments).
For each: download the Excel/CSV template (the Excel one has an *Instructions* sheet), fill it, upload it and review the check — nothing is saved until you confirm.
Rows with errors can be downloaded, corrected and uploaded again. Very large files can be imported from the command line:

```
php artisan emr:import                          # list import types
php artisan emr:import patients old.xlsx        # check only
php artisan emr:import patients old.xlsx --commit [--update]
```

## Updating an existing installation

After pulling new code, always run (with MySQL running):

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # adds new permissions to built-in roles
php artisan optimize:clear
```

## Security & backups (Module 13)

- **Idle sign-out** (Hospital Settings → Security, default 30 min) with a one-minute warning; typing counts as activity.
- **Account lockout** after 5 wrong passwords (15 min); unlock from the staff profile. Previous sign-in time/IP is shown on the dashboard.
- **Security headers** on every page; signed-in pages are never cached by the browser.
- **CSV exports** neutralise spreadsheet formulas (CSV injection).
- **Backups:** `php artisan emr:backup` (nightly at 01:30) zips the database dump plus `storage/app/private` and `public/uploads` into
  `storage/app/backups`, keeping the number of days set in Security settings. Copy backups off the server regularly.
  mysqldump is auto-detected on XAMPP; otherwise set `EMR_MYSQLDUMP=/path/to/mysqldump` in `.env`.
- **System Health** (Administration) checks debug mode, environment, HTTPS, scheduler, backups, disk space and pending migrations.

### Production `.env`

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-hospital-domain
SESSION_SECURE_COOKIE=true   # when served over HTTPS
```
