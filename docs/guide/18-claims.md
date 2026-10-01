# Insurance, HMO and NHIA claims

**Menus:** Finance & Reports → Insurance Claims, PA Codes · **Used by:** accountants / HMO desk (claims), cashiers and records officers (PA codes)

## Pre-authorisation (PA) codes

Many HMOs (and NHIA for secondary care) require a PA code before a service can be claimed.

1. On the patient's billing account, use **Request PA**: describe the services, diagnosis and estimated cost, and optionally attach the bill.
2. Contact the HMO as usual. When they reply, open **PA Codes**, find the request under "Awaiting HMO" and record **Approved** with the code, approved amount and expiry — or **Declined**.
3. An approved code is copied onto the attached bill automatically. Codes can also be typed directly on a bill in the claims list.

Insurers marked "requires a PA code" (Administration → Insurance / HMOs) cannot have bills batched without one.

## From bills to payment

| Step | Where | What happens |
|---|---|---|
| 1. To batch | Insurance Claims → To batch | Bills with an insurer share, grouped by payer. Open visits, current admissions and bills missing a required PA code cannot be selected yet. |
| 2. Create batch | Tick bills → Create batch | A draft batch (CLM…) is created; bills can still be removed. |
| 3. Submit | Batch → Mark submitted | Amounts are frozen; print the claim schedule and claim forms, export CSV, or send electronically. |
| 4. Remittance | Batch → enter paid amounts | Enter what the payer paid per bill (0 = rejected) and the reason for any shortfall. Bills become Paid, Part-paid or Rejected. |
| 5. Follow up | Rejected & short-paid tab | **Resubmit** a corrected rejected bill in a new batch, or **Bill patient** to move the unpaid share to the patient's account. Leaving it there writes it off. |

## Documents

- **Claim schedule** — one line per encounter: enrollee ID, PA code, diagnoses with ICD-10 codes, amount; with signature lines.
- **Claim form** (per bill) — enrollee and encounter details, diagnoses, itemised services, co-payment, signature lines.
- **CSV export** — one row per service line, for HMOs that accept spreadsheets.
- **e-Claim file (JSON)** — a structured electronic claim of the whole batch.

## Sending claims electronically

If a claims API has been set up (Hospital Settings → Integrations), a submitted batch shows **Send electronically**. The batch is sent with the facility code, and the payer's reference is shown on the batch.

> NHIA and HMOs use different electronic systems. The electronic file uses common claim fields; the hospital must confirm the exact format with each payer before sending live claims.

## Reports

**Insurance claims summary** (by payer and status, with rejection rate) and **Unpaid claims ageing** (what each payer owes, by 0–30, 31–60, 61–90 and 90+ days since submission) are under Reports.
