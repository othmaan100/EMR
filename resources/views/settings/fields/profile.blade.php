@php($s = app(\App\Support\Settings::class)->all())
<div class="row g-3">
    <x-form.input name="hospital_name" label="Hospital name" :value="$s['hospital_name'] ?? ''" required col="col-md-8" placeholder="e.g. St. Mary's General Hospital" autofocus />
    <x-form.input name="hospital_short_name" label="Short name / abbreviation" :value="$s['hospital_short_name'] ?? ''" col="col-md-4" placeholder="e.g. SMGH" help="Shown in the sidebar and on receipts." />
    <x-form.select name="hospital_type" label="Facility type" :options="array_combine(config('emr.hospital_types'), config('emr.hospital_types'))" :value="$s['hospital_type'] ?? ''" required placeholder="Select..." col="col-md-6" />
    <x-form.input name="registration_number" label="Registration / licence number" :value="$s['registration_number'] ?? ''" col="col-md-6" />
    <x-form.input name="motto" label="Motto / tagline" :value="$s['motto'] ?? ''" col="col-12" placeholder="Printed under the hospital name on reports" />

    <div class="col-12">
        <label for="logo" class="form-label">Hospital logo</label>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <img id="logoPreview" src="{{ ! empty($s['logo']) ? asset($s['logo']) : '' }}" alt="Logo preview" @class(['logo-preview', 'd-none' => empty($s['logo'])])>
            <div class="flex-grow-1">
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" data-preview="#logoPreview"
                       @class(['form-control', 'is-invalid' => $errors->has('logo')])>
                <div class="form-text">PNG, JPG or WEBP, max 2 MB. A square image with a transparent background works best.</div>
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if (! empty($s['logo']))
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                        <label class="form-check-label" for="remove_logo">Remove current logo</label>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
