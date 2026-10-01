@php($s = app(\App\Support\Settings::class)->all())
<div class="row g-3">
    <x-form.input name="address" label="Street address" :value="$s['address'] ?? ''" required col="col-12" autofocus />
    <x-form.input name="city" label="City / Town" :value="$s['city'] ?? ''" required col="col-md-6" />
    <x-form.input name="state" label="State / Province / Region" :value="$s['state'] ?? ''" col="col-md-6" />
    <x-form.input name="country" label="Country" :value="$s['country'] ?? ''" required col="col-md-6" />
    <x-form.input name="postal_code" label="Postal code" :value="$s['postal_code'] ?? ''" col="col-md-6" />
    <x-form.input name="phone" type="tel" label="Phone" :value="$s['phone'] ?? ''" required col="col-md-6" />
    <x-form.input name="alt_phone" type="tel" label="Alternative phone" :value="$s['alt_phone'] ?? ''" col="col-md-6" />
    <x-form.input name="email" type="email" label="Email" :value="$s['email'] ?? ''" col="col-md-6" />
    <x-form.input name="website" type="url" label="Website" :value="$s['website'] ?? ''" col="col-md-6" placeholder="https://" />
</div>
