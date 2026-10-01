@php
    $currencies = collect(config('emr.currencies'))->mapWithKeys(fn ($c, $code) => [$code => "$code — {$c['name']} ({$c['symbol']})"])->all();
    $dateFormats = collect(config('emr.date_formats'))->mapWithKeys(fn ($example, $fmt) => [$fmt => $example])->all();
@endphp
<div class="row g-3">
    <x-form.select name="currency_code" label="Currency" :options="$currencies" :value="setting('currency_code')" required col="col-md-6" />
    <x-form.select name="timezone" label="Time zone" :options="array_combine($timezones, $timezones)" :value="setting('timezone')" required col="col-md-6" />
    <x-form.select name="date_format" label="Date format" :options="$dateFormats" :value="setting('date_format')" required col="col-md-6" />
    <x-form.input name="patient_number_prefix" label="Patient number prefix" :value="setting('patient_number_prefix')" required col="col-md-6"
                  maxlength="10" help="Hospital numbers will look like PREFIX-000123." />

    <div class="col-md-6">
        <label for="primary_color" class="form-label">Brand colour <span class="text-danger">*</span></label>
        <div class="d-flex gap-2 align-items-center">
            <input type="color" id="primary_color" name="primary_color" value="{{ old('primary_color', setting('primary_color')) }}"
                   @class(['form-control form-control-color', 'is-invalid' => $errors->has('primary_color')])>
            <span class="form-text m-0">Used for buttons, highlights and printed headers.</span>
        </div>
        @error('primary_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="bill_before_service" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="bill_before_service" value="1" id="bill_before_service"
                   @checked(old('bill_before_service', setting('bill_before_service')))>
            <label class="form-check-label" for="bill_before_service">
                Patients must pay before lab samples are collected and imaging is performed
                <span class="d-block form-text mt-0">Applies to the patient's own share; insurance-covered amounts never block service.</span>
            </label>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="portal_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="portal_enabled" value="1" id="portal_enabled"
                   @checked(old('portal_enabled', setting('portal_enabled')))>
            <label class="form-check-label" for="portal_enabled">
                Patient portal
                <span class="d-block form-text mt-0">Patients sign in at <code>{{ url('portal') }}</code> to see appointments, released results, bills and immunizations. Staff give access from the patient folder.</span>
            </label>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="pharmacy_pay_first" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="pharmacy_pay_first" value="1" id="pharmacy_pay_first"
                   @checked(old('pharmacy_pay_first', setting('pharmacy_pay_first')))>
            <label class="form-check-label" for="pharmacy_pay_first">
                Pharmacy pay-first: price the prescription, patient pays at the cashier, then drugs are dispensed
                <span class="d-block form-text mt-0">Insurance-covered shares never block dispensing. When off, drugs are charged as they are dispensed.</span>
            </label>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="lab_self_verify" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="lab_self_verify" value="1" id="lab_self_verify"
                   @checked(old('lab_self_verify', setting('lab_self_verify')))>
            <label class="form-check-label" for="lab_self_verify">
                Allow the same person to enter <em>and</em> verify lab results
                <span class="d-block form-text mt-0">Only for small labs with a single scientist on duty. Otherwise a second person must verify.</span>
            </label>
        </div>
    </div>
</div>
