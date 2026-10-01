# Data import from a previous system

**Menu:** Administration → Data Import (permission "Import data"; importing staff accounts also needs "Create, edit & deactivate staff")

Data Import brings records from an old EMR, spreadsheets or paper registers into the system — for go-live and to keep it up to date.

## Import types, in the order to import them

Later imports refer to earlier ones by code (for example, patients refer to insurers by insurer code), so work through the steps in order.

{{import-types}}

## How to import

1. Open the import type and download the **template** — Excel (.xlsx, with an **Instructions** sheet explaining every column, allowed values and examples) or CSV. A **sample file** with example rows is also available.
2. Fill one record per row. Keep the column headings. Red headings are required. Format phone-number columns as **Text** in Excel.
3. Upload the file and choose what happens to records that already exist: **skip them** (add new only) or **update them** (blank cells keep the current value).
4. **Review the check.** Nothing is saved yet. You see how many rows will be added, updated or skipped, and every row with a problem — with its row number and the reason.
5. Select **Import** to save the valid rows, or **Cancel**. Rows with errors are left out: **Download rows with errors**, correct them and upload that file.

## Helpful behaviour

- Dates are accepted as 2024-01-31, 31/01/2024, "31 Jan 2024" or Excel dates; impossible dates are rejected.
- Common variants are understood: M/F, "O Positive", "Cash", "NHIS", yes/no.
- Phone numbers that lost their leading 0 in Excel are corrected for Nigerian mobiles.
- Patients without a date of birth can be given an age instead (birth date marked estimated).
- Old hospital numbers can be kept, or stored as a searchable legacy number. New registrations continue above imported numbers.
- The same record twice in one file is reported. Re-uploading a stock file does not double-count stock.
- Imported staff must change their password at first sign-in; the Super Admin role cannot be given by import.

## History

**Import history** lists every import with who ran it, counts and errors. One summary entry is written to the audit log for each import.

Very large files (over 50,000 rows) can be imported by the IT officer from the command line (see Part D).
