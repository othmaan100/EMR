# Appointments, check-in and the clinic queue

**Menus:** Clinical → Appointments, Clinical → Clinic Queue · **Used by:** records officers, nurses, doctors

## Booking an appointment

1. From the patient folder select **Book**, or go to **Appointments → Book appointment**.
2. Choose the clinic, date, time, type (new, follow-up, result review, procedure), optionally a doctor, and the reason.
3. Save. If SMS reminders are switched on, the patient receives a reminder the day before.

The **Appointments** page shows one day at a time; use the arrows or the date box to move between days, and filter by clinic, doctor or status. From the row menu you can **reschedule**, **cancel** (a reason is required) or mark a **no-show**. Appointments still "scheduled" after their day show as **Missed**.

**Online requests:** patients using the portal can request an appointment for a clinic, a preferred date and morning/afternoon. They appear under **Appointments → Online requests** (and a dashboard tile). Choose the exact date, time and doctor and select **Confirm**, or **Cancel** with a reason the patient will see. A confirmation SMS is sent if SMS reminders are on.

## Checking a patient in

- **With an appointment:** on the day, select **Check in** on the appointment row.
- **Walk-in:** open the patient folder and select **Check in**, choose the clinic, visit type and priority (Normal, Urgent, Emergency).

Check-in creates a **visit** with a queue number (e.g. GOPD-007) and charges the clinic's consultation fee if priced. If the clinic requires triage, the patient goes to **Waiting for triage**; otherwise straight to **Waiting for doctor**.

## The clinic queue

**Clinic Queue** lists today's patients by clinic and status, ordered by priority (Emergency first) and arrival time. Staff with queue permission can move a patient between stages:

| From | Can move to |
|---|---|
| Waiting for triage | Waiting for doctor, Left without being seen, Cancelled |
| Waiting for doctor | In consultation, back to triage, Left, Cancelled |
| In consultation | Completed, back to Waiting for doctor |

Doctors see "My patients" and start a consultation from the queue. Signing the consultation completes the visit.
