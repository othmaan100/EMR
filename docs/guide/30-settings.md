# First-time setup and hospital settings

## Setup wizard

When the system is installed for a new hospital, the first visit opens the setup wizard:

1. **System check** — confirms the server meets the requirements.
2. **Hospital profile** — name, short name, type, registration number, motto and logo.
3. **Contact & address** — address, city, state, country, phones, email, website.
4. **Preferences** — currency, time zone, date format, hospital number prefix (e.g. PT) and brand colour.
5. **Administrator** — the first Super Admin account.

The wizard runs once. Afterwards the details are changed in **Hospital Settings**. Because every hospital-specific detail lives in settings, the same software serves any hospital — each installation has its own database.

## Hospital Settings tabs

**Menu:** Administration → Hospital Settings (permission "Manage hospital settings")

| Tab | Contents |
|---|---|
| Hospital Profile | Name, logo and details printed on every document |
| Contact & Address | Shown on letterheads, receipts and the patient portal |
| Preferences | Currency, time zone, date format, hospital-number prefix, colour, and switches: lab self-verification, pay before service (lab and imaging), pharmacy pay-first, patient portal |
| Security & backups | Idle sign-out time (5–480 minutes), how many days of backups to keep |
| SMS messaging | Provider, sender ID, keys, automatic message switches and templates |
| Integrations | PACS, online payment gateway, electronic claims, NIN verification |

Keys and passwords are stored encrypted and never shown again; leave such a field blank to keep the saved value. Every change to settings is recorded in the audit log.
