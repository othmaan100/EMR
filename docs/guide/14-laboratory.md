# Laboratory

**Menu:** Clinical → Laboratory · **Used by:** lab scientists; doctors view released results

## How a lab order moves

| Status | Meaning |
|---|---|
| Requested | Ordered by a doctor; waiting for the sample |
| Collected | Sample taken and labelled |
| In progress | All results entered; waiting for verification |
| Completed | Verified and released — visible to doctors, on reports and (if enabled) to the patient |
| Rejected / cancelled | Sample rejected (with reason) or order cancelled |

## Collecting a sample

1. Open **Laboratory**; the "To collect" tab lists requested orders (urgent first).
2. Select **Collect** on the list (or open the order and **Mark sample collected**). If the hospital requires payment before service, the system checks the patient has paid first.
3. Print the **sample label** — its barcode is the lab order number (e.g. LAB2026-000123), which analysers read as the sample ID.

**Rejecting a sample** (haemolysed, clotted, insufficient, unlabelled…): select **Reject**, give the reason; the doctor sees it and can re-order.

## Entering results

Open the order and type the results. Tests with set-up result fields show each field with its unit and normal range; values outside the range are flagged **L** (low) or **H** (high) automatically. Tests without fields accept a free-text result. Add a comment where needed and **Save results**.

Results from a connected **analyser** arrive automatically and appear as entered by the analyser; review them like any other results.

## Verifying and releasing

A second scientist opens the order from the **To verify** tab and selects **Verify**. The person who entered results cannot verify them, unless the hospital has switched on "self-verification" for single-scientist labs (Hospital Settings → Preferences). Verification releases the results: the doctor sees them, the report can be printed, the patient can see them in the portal, and a "results ready" SMS is sent if enabled.

## Lab report

**Print report** produces a report with the letterhead, patient details, clinical diagnosis, each result with flag and range, who entered and who verified.

## Setting up tests (administrators)

Tests are managed under Administration → Clinical Catalogues → Lab Tests. Select a test and **Result fields** to add parameters (numeric with normal range, a list of options, or text).
