@php
    $secret = function (string $name, string $label, ?string $help = null) {
        return ['name' => $name, 'label' => $label, 'help' => $help, 'saved' => filled(setting($name))];
    };
    $switch = fn (string $name, string $label, ?string $help = null) => compact('name', 'label', 'help');
@endphp

<div class="alert alert-light border small">
    Each integration needs an account or server from the provider. Keys are stored encrypted and never shown again — leave a key field blank to keep the saved one.
    Test the connections and watch the message log on <a href="{{ route('admin.integrations.index') }}">Administration → Integrations</a>.
</div>

{{-- ------------------------------------------------------------------ PACS --}}
<h6 class="text-muted text-uppercase small fw-semibold mb-2">Radiology images — PACS (DICOMweb)</h6>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="pacs_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="pacs_enabled" value="1" id="pacs_enabled" @checked(old('pacs_enabled', setting('pacs_enabled')))>
            <label class="form-check-label" for="pacs_enabled">Link imaging orders to studies in the PACS
                <span class="d-block form-text mt-0">The order number (e.g. IMG2026-000123) must be entered as the Accession Number at the modality.</span></label>
        </div>
    </div>
    <x-form.input name="pacs_dicomweb_url" label="DICOMweb base URL" :value="setting('pacs_dicomweb_url')" col="col-md-6" placeholder="http://pacs.local:8042/dicom-web" help="Orthanc: http://server:8042/dicom-web" />
    <x-form.input name="pacs_viewer_url" label="Viewer link" :value="setting('pacs_viewer_url')" col="col-md-6" placeholder="http://pacs.local:8042/ohif/viewer?StudyInstanceUIDs={study}"
                  help="Use {study} for the Study Instance UID (or {accession})." />
    <x-form.input name="pacs_username" label="Username" :value="setting('pacs_username')" col="col-md-6" />
    @include('settings.fields._secret', $secret('pacs_password', 'Password'))
</div>

{{-- ------------------------------------------------------------------ Payments --}}
<h6 class="text-muted text-uppercase small fw-semibold mb-2">Online payments</h6>
<div class="row g-3 mb-4">
    <x-form.select name="payment_gateway" label="Payment gateway" :options="['none' => 'Not set up', 'paystack' => 'Paystack', 'flutterwave' => 'Flutterwave']"
                   :value="setting('payment_gateway', 'none')" required col="col-md-6" />
    @include('settings.fields._secret', $secret('payment_secret_key', 'Secret key', 'Paystack: sk_live_… · Flutterwave: FLWSECK-…'))
    @include('settings.fields._secret', $secret('payment_webhook_hash', 'Flutterwave webhook secret hash', 'Only for Flutterwave: the "secret hash" set on its webhook page.'))
    <div class="col-md-6">
        <label class="form-label">Webhook URL (give this to the gateway)</label>
        <input type="text" readonly class="form-control form-control-sm font-monospace" value="{{ url('api/v1/payments/webhook/'.(setting('payment_gateway') ?: 'paystack')) }}" aria-label="Webhook URL">
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="portal_online_payments" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="portal_online_payments" value="1" id="portal_online_payments" @checked(old('portal_online_payments', setting('portal_online_payments')))>
            <label class="form-check-label" for="portal_online_payments">Let patients pay their bills in the patient portal</label>
        </div>
    </div>
</div>

{{-- ------------------------------------------------------------------ Claims --}}
<h6 class="text-muted text-uppercase small fw-semibold mb-2">NHIA / HMO electronic claims</h6>
<div class="row g-3 mb-4">
    <div class="col-12 small text-muted">NHIA and HMOs use different systems. The claim file follows common claim fields; confirm the format with your payer before sending live claims.</div>
    <x-form.input name="claims_provider_code" label="Facility / provider code" :value="setting('claims_provider_code')" col="col-md-4" help="Your NHIA facility or HMO provider code." />
    <x-form.input name="claims_api_url" label="Claims API URL (https)" :value="setting('claims_api_url')" col="col-md-8" placeholder="https://claims.example-hmo.ng/api/batches" />
    @include('settings.fields._secret', $secret('claims_api_key', 'Claims API key'))
</div>

{{-- ------------------------------------------------------------------ NIN --}}
<h6 class="text-muted text-uppercase small fw-semibold mb-2">National ID (NIN) verification</h6>
<div class="row g-3">
    <div class="col-12 small text-muted">NIN lookups are only allowed through NIMC-licensed partners. Tell patients before looking up their NIN; only name, date of birth, sex and phone are used.</div>
    <x-form.select name="nin_provider" label="Verification provider" :options="\App\Integrations\Identity\NinVerifier::PROVIDERS" :value="setting('nin_provider', 'none')" required col="col-md-4" />
    <x-form.input name="nin_app_id" label="App ID" :value="setting('nin_app_id')" col="col-md-4" />
    @include('settings.fields._secret', $secret('nin_api_key', 'API / secret key'))
    <x-form.input name="nin_base_url" label="Custom API base URL (optional)" :value="setting('nin_base_url')" col="col-md-6" help="Only if your provider gave you a different endpoint." />
</div>
