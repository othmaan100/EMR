# Introduction

## About this guide

This guide explains how to use the {{hospital}} Electronic Medical Records and Hospital Management System ("the EMR"). It is written for everyone who uses the system: records officers, nurses, midwives, doctors, laboratory, radiology and pharmacy staff, cashiers, accountants, storekeepers, managers and system administrators.

- **Part A** — getting started, and what each staff role can do.
- **Part B** — one chapter per module, with step-by-step instructions for everyday tasks.
- **Part C** — administration: settings, staff and roles, catalogues, data import, integrations, backups and security.
- **Part D** — technical information for the IT officer: installation, scheduled tasks, commands and the API.

> Screens show only what your role allows. If a button described here is missing on your screen, your role does not include that permission; ask the system administrator.

## What the system covers

The EMR follows the patient from the front desk to discharge, and runs the hospital's supporting services:

| Area | What it does |
|---|---|
| Front desk | Patient registration, hospital cards, appointments, check-in and clinic queues, online appointment requests |
| Clinical care | Triage and vital signs with early-warning scores, consultations, ICD-10 diagnoses, prescriptions, lab and imaging orders |
| Diagnostics | Laboratory (sample collection, results, two-person verification, analyser link) and radiology (scheduling, images, reports, PACS link) |
| Pharmacy | Dispensing (earliest expiry first), drug stock by batch, receiving, adjustments, reorder alerts, optional pay-first |
| Inpatients | Wards and beds, admission, transfer, discharge summary, drug chart, ward notes, daily bed charges |
| Maternity and child health | Antenatal care, partograph, delivery and baby registration, postnatal care, immunization and defaulter tracing |
| Theatre | Booking, pre-operative assessment, WHO surgical safety checklist, anaesthesia record, operation notes |
| Specialty clinics | Dental chart, eye examinations and spectacle prescriptions, physiotherapy courses |
| Finance | Price list (standard and per insurer), billing, cashier and receipts, deposits, discounts, HMO/NHIA claims with PA codes, online payments |
| Stores | General store, requisitions, purchase orders, deliveries and supplier invoices |
| Patients | Patient portal: appointments, released results, bills and online payment, immunization card |
| Communication | SMS reminders and "results ready" messages |
| Management | Reports and analytics, audit trail, backups, system health, data import from old systems, integrations |

## Words used in this guide

| Term | Meaning |
|---|---|
| Hospital number | The patient's unique number, e.g. PT-000123. Printed on the hospital card. |
| Visit | One attendance, from check-in until the consultation is signed or the patient leaves. |
| Order | A request for a lab test, imaging examination or medicine. |
| Verify / release | Lab results and imaging reports are only visible to doctors (and patients) after a second person verifies them or the radiologist signs. |
| Bill item | One charge on a patient's account (e.g. a consultation fee or a test). |
| PA code | Pre-authorisation code issued by an HMO before a service is claimed. |
| Role | A set of permissions (e.g. Nurse, Cashier). A staff member can have more than one role. |
