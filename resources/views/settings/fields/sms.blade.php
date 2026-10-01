@php
    $provider = old('sms_provider', setting('sms_provider', 'none'));
    $hasKey = filled(setting('sms_api_key'));
    $switches = [
        'sms_appointment_reminders' => ['Appointment reminders', 'Sent the day before, at 8:00 AM.'],
        'sms_results_ready' => ['Results ready', 'When lab results are verified or an imaging report is signed.'],
        'sms_immunization_reminders' => ['Immunization reminders', "Sent to the carer 3 days before a child's dose is due."],
    ];
    $templates = [
        'sms_tpl_appointment' => ['Appointment reminder', '{name} {date} {time} {clinic} {hospital} {phone}'],
        'sms_tpl_results' => ['Results ready', '{name} {test} {ref} {hospital} {phone}'],
        'sms_tpl_immunization' => ['Immunization reminder', '{name} {vaccine} {date} {hospital} {phone}'],
    ];
@endphp

<h6 class="text-muted text-uppercase small fw-semibold mb-3">Provider</h6>
<div class="row g-3">
    <x-form.select name="sms_provider" label="SMS provider" :options="\App\Services\SmsService::PROVIDERS" :value="$provider" required col="col-md-6"
                   help="While set to “Log only”, messages are recorded in SMS Messages but not sent." />
    <x-form.input name="sms_sender_id" label="Sender ID / From number" :value="setting('sms_sender_id')" col="col-md-6" maxlength="20"
                  help="Termii / Africa's Talking: approved sender ID. Twilio: your Twilio number, e.g. +15551234567." />
    <x-form.input name="sms_username" label="Username / Account SID" :value="setting('sms_username')" col="col-md-6"
                  help="Africa's Talking username (use “sandbox” for testing) or Twilio Account SID. Not needed for Termii." />
    <div class="col-md-6">
        <label for="sms_api_key" class="form-label">API key / Auth token</label>
        <input type="password" id="sms_api_key" name="sms_api_key" autocomplete="new-password" @class(['form-control', 'is-invalid' => $errors->has('sms_api_key')])
               placeholder="{{ $hasKey ? '•••••••• saved — leave blank to keep' : 'Not set' }}">
        <div class="form-text">Stored encrypted; never displayed again.</div>
        @error('sms_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <x-form.input name="sms_country_code" label="Default country code" :value="setting('sms_country_code')" required col="col-md-6" maxlength="4"
                  help="Local numbers like 0803… become 234803…" />
    <x-form.input name="sms_base_url" label="Termii base URL (optional)" :value="setting('sms_base_url')" col="col-md-6"
                  help="Only if Termii gave you a dedicated endpoint (https)." />
</div>

<h6 class="text-muted text-uppercase small fw-semibold mt-4 mb-3">Automatic messages</h6>
@foreach ($switches as $key => [$label, $help])
    <div class="form-check form-switch mb-2">
        <input type="hidden" name="{{ $key }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="{{ $key }}" @checked(old($key, setting($key)))>
        <label class="form-check-label" for="{{ $key }}">{{ $label }} <span class="d-block form-text mt-0">{{ $help }}</span></label>
    </div>
@endforeach

<h6 class="text-muted text-uppercase small fw-semibold mt-4 mb-3">Message templates</h6>
@foreach ($templates as $key => [$label, $placeholders])
    <x-form.textarea :name="$key" :label="$label" rows="2" maxlength="480"
                     :value="setting($key) ?: \App\Services\SmsNotifier::DEFAULT_TEMPLATES[$key]"
                     :help="'Placeholders: '.$placeholders.'. One SMS is 160 characters; longer messages cost more.'" />
@endforeach
