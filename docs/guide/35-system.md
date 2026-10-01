# System health, backups, audit and security

## System Health & Backups

**Menu:** Administration → System Health & Backups (permission "System health & backups")

The health page checks: debug mode, environment, HTTPS, the scheduler, backups, SMS, disk space, storage folder, database updates and PHP version. Problems are shown with how to fix them, and a warning appears on the administrator's dashboard.

**Backups** run every night at 1:30 am (the scheduled task must be running). Each backup is a zip file containing the database and the private files (patient photos, imaging uploads) and the uploaded logo. Old backups are removed after the number of days set in Security & backups. Select **Back up now** before any major change. Only a Super Admin can download a backup.

> A backup on the same computer does not protect against theft, fire or disk failure. Copy backups to another location (external drive or cloud storage) regularly, and test restoring one.

## Audit log

**Menu:** Administration → Audit Log. Records who did what and when: sign-ins and failures, patient records opened, changes to records (with old and new values), settings, roles and permissions, payments, discounts, voids and reversals, results released, imports, integrations and more. Filter by user, event, date or record. Entries cannot be edited or deleted.

## Security features

- Passwords stored hashed; at least 8 characters with letters and numbers; forced change after an administrator sets a password.
- Account lockout after 5 failed sign-ins (15 minutes); idle sign-out.
- Role-based permissions on every page and action.
- Patient photos, imaging files and backups stored outside the public web folder.
- Security headers on every page; CSV exports protected against spreadsheet formula injection.
- Integration keys and passwords encrypted in the database.

## Protecting patient data

Patient information is confidential and protected by the Nigeria Data Protection Act. Only open records you need for your work (opening is logged), never share passwords, sign out of shared computers, and report suspected misuse to the administrator.
