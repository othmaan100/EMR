<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <x-form.input name="name" label="Name" :value="$provider->name" required col="col-md-8" autofocus />
            <x-form.input name="code" label="Code" :value="$provider->code" required col="col-md-4" maxlength="20" />
            <x-form.select name="type" label="Type" :options="['insurance' => 'Insurance / HMO', 'corporate' => 'Corporate / Company']" :value="$provider->type" required col="col-md-6" />
            <x-form.input name="coverage_percent" type="number" label="Coverage (%)" :value="$provider->coverage_percent ?? 100" required col="col-md-6"
                          min="0" max="100" help="Share of each bill item paid by this payer; the patient pays the rest (co-pay)." />
            <x-form.input name="contact_person" label="Contact person" :value="$provider->contact_person" col="col-md-6" />
            <x-form.input name="phone" type="tel" label="Phone" :value="$provider->phone" col="col-md-6" />
            <x-form.input name="email" type="email" label="Email" :value="$provider->email" col="col-md-6" />
            <x-form.input name="address" label="Address" :value="$provider->address" col="col-12" />
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $provider->is_active))>
                    <label class="form-check-label" for="is_active">Active (available when registering patients)</label>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="requires_authorization" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="requires_authorization" value="1" id="requires_authorization"
                           @checked(old('requires_authorization', $provider->requires_authorization))>
                    <label class="form-check-label" for="requires_authorization">
                        Requires a pre-authorisation (PA) code on every claim
                        <span class="d-block form-text mt-0">Bills without a PA code cannot be put in a claim batch for this payer (common for NHIA secondary care).</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
