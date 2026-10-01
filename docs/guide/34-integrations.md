# Integrations

**Menus:** Administration → Integrations (status, connection tests, lab analysers, message log) and Hospital Settings → Integrations (credentials) · **Permission:** "Set up integrations"

Every message to or from an outside system is listed in the **message log** with its status (ok, partial, error). Errors today are shown at the top of the Integrations page.

> Each integration needs an account, licence or server from the provider. The connections follow the providers' published interfaces; test each one with the provider before relying on it.

## Laboratory analysers

Analysers (or the lab middleware connected to them) send results to the EMR over the network.

1. **Integrations → Add analyser**: name and code. A secret **API token** is shown once — copy it into the analyser/middleware settings. **New token** replaces it if lost.
2. **Map codes:** for each code the analyser sends (e.g. HGB, WBC), choose the test and result field it belongs to.
3. Configure the analyser/middleware to send results to `{{url}}/api/v1/lab/results` with the header `Authorization: Bearer <token>`, as **HL7 v2 ORU^R01** messages or JSON.
4. Print sample labels as usual: the barcode (lab order number) is the sample ID the analyser reports back.

Results are filed on the matching order as **entered by the analyser** and must still be **verified** by a scientist. Unmapped codes are skipped and reported in the log; results never overwrite verified results. Instruments that only use ASTM or a serial (RS-232) cable need a small middleware or serial-to-network bridge.

## PACS — radiology images (DICOM)

For a PACS with DICOMweb (for example the free Orthanc server, dcm4chee, or most vendor PACS):

1. Hospital Settings → Integrations: switch on the PACS link, enter the **DICOMweb URL**, username and password, and the **viewer link** (with {study} where the study ID goes).
2. Integrations → **Test connection**.
3. Radiographers enter the imaging order number as the **Accession Number** at the modality.

Every five minutes the system looks for studies of recent orders, links them, marks the order performed (shown as "PACS link"), and shows **View images** on the order.

## Online payments — Paystack or Flutterwave

1. Open a merchant account with **Paystack** or **Flutterwave**.
2. Hospital Settings → Integrations: choose the gateway and enter the **secret key** (and, for Flutterwave, the webhook secret hash).
3. In the gateway's dashboard, set the **webhook URL** shown on the settings page.
4. Optionally switch on **portal payments**.

Payments are always verified with the gateway (server to server) before they are recorded, so a payment cannot be faked by a browser. Webhooks are checked by signature. If both the patient's return and the webhook arrive, the payment is recorded once.

## NHIA / HMO electronic claims

Enter the facility/provider code, the claims API URL (https) and API key. Submitted claim batches then show **Send electronically**; the e-claim file (JSON) can be downloaded at any time.

> NHIA and HMOs do not share one public claims interface. The electronic file uses common claim fields (enrollee, PA code, ICD-10 diagnoses, itemised services). Confirm the format with each payer, and have the IT officer adapt it if required, before sending live claims.

## National ID (NIN) verification

NIN lookups are allowed only through NIMC-licensed verification partners. Choose **Dojah** or **Prembly (IdentityPass)**, enter the App ID and key from your contract (and a custom URL if the provider gives one). A **Verify** button then appears on the patient registration form.

Only the name, date of birth, sex and phone are used, to fill the form; nothing else from the response is stored. The log shows a masked NIN (e.g. 123*****901). Obtain the patient's consent before verifying.
