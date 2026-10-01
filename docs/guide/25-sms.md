# SMS messaging

**Set up:** Hospital Settings → SMS messaging · **Log:** Administration → SMS Messages

## Automatic messages

| Message | When | Switch |
|---|---|---|
| Appointment reminder | 8:00 am the day before the appointment | Appointment reminders |
| Appointment confirmed | When staff confirm an online request | Appointment reminders |
| Results ready | When lab results are verified or an imaging report is signed (the message never names the test) | Results ready |
| Immunization reminder | To the carer, 3 days before a dose is due | Immunization reminders |
| Portal activation code | When staff give portal access and tick "send by SMS" | — |
| Payment link | When the cashier creates an online payment link and ticks "SMS" | — |

Each automatic message has an editable **template** with placeholders such as {name}, {date}, {time}, {clinic} and {hospital}. A person never receives the same reminder twice.

## Provider

Choose **Termii**, **Africa's Talking** or **Twilio** and enter the sender ID and API key (stored encrypted). While the provider is "Log only", messages are recorded but not sent — useful for testing. Messages are sent every minute by the scheduled task; failures are retried three times.

## SMS log

**SMS Messages** shows every message with its status (queued, sent, failed, logged only), lets you search by phone or patient, **Retry** failed messages and **Send a test message**. One-time codes are blanked in the log once sent.
