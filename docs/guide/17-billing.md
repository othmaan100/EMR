# Billing and the cashier

**Menus:** Finance & Reports → Billing & Cashier, Price List · **Used by:** cashiers, accountants, administrators

## How charges reach the bill

Charges are added automatically when the service happens — nobody has to "raise a bill":

| Event | Charge (if priced) |
|---|---|
| Registration | Registration / card fee |
| Check-in | The clinic's consultation fee (or the general consultation fee) |
| Lab / imaging order | The test or examination |
| Dispensing (or pricing, under pay-first) | The drug, per unit |
| Admission | Daily bed charge (posted nightly) |
| Procedures | Theatre fee, operation, delivery, ANC booking, dental/eye/physio services |

Each visit or admission has its own bill (invoice); registration and other charges go on a separate bill. **Items without a price are never charged** — set prices in the Price List.

**Insurance and company patients:** each charge is split automatically into the payer's share (by the payer's coverage %, using the payer's own tariff if one is set) and the patient's share (co-pay). The payer's share goes to Insurance Claims. **Free / waiver** patients have their share written off automatically.

## Taking a payment

1. Open **Billing & Cashier** and search for the patient, or select **Account** in the patient folder.
2. Tick the items being paid (oldest first is suggested) and enter the amount.
3. Choose the method — cash, card/POS, bank transfer, mobile money or cheque — and the reference for non-cash payments.
4. Select **Receive payment**. A numbered receipt (RCT…) prints with the letterhead.

A payment can cover part of an item; it fills the oldest items first.

**Deposits:** select **Take deposit** to receive money in advance (e.g. on admission). Later, choose "deposit" as the method to pay items from it. The account shows the deposit available.

**Manual charges:** **Add charge** puts any priced service on the bill (e.g. a procedure done on the ward).

## Online payments

If an online payment gateway is set up (Hospital Settings → Integrations):

- Select **Online payment link** on the patient's account to create a link for the unpaid items, and optionally send it by SMS. The patient pays by card, bank transfer or USSD.
- Patients using the portal can **Pay online** from their bills page.

Payments are confirmed with the gateway before they are recorded, appear as method "Online" with the gateway reference, and print a normal receipt. If the patient had already paid at the cashier meanwhile, the online amount is kept as a deposit — never lost.

## Discounts, voids and reversals (need extra permissions)

- **Discount / waive** part or all of the patient's unpaid share — reason required.
- **Void charge** for a charge made in error — only if nothing has been paid on it.
- **Reverse** a payment received in error — reason required; the items become unpaid again.

All three are recorded in the audit log with who did it and why.

## Pay before service

If **"Patients must pay before…"** is switched on (Hospital Settings → Preferences), the lab cannot collect a sample and radiology cannot perform an examination until the patient's share is paid. The pharmacy has its own pay-first setting. Insurance-covered shares never block service.

## Invoices and receipts

From the account, print an **invoice** for any bill (with the payer and patient shares), or reprint any **receipt**.

## Price list

**Price List** (permission "Set prices") shows services, lab tests, imaging, drugs (per unit) and surgery. Choose "Standard" or an insurer, type the prices and save. Insurer-specific prices override the standard price for that insurer's patients.
