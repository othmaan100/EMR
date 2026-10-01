<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <x-form.input name="name" label="Clinic name" :value="$clinic->name" required col="col-md-8" autofocus placeholder="e.g. General Outpatient Clinic" />
            <x-form.input name="code" label="Code" :value="$clinic->code" required col="col-md-4" maxlength="10" placeholder="e.g. GOPD" help="Prefix for queue numbers (GOPD-001)." />
            <x-form.select name="department_id" label="Department" :options="$departments->all()" :value="$clinic->department_id" placeholder="— None —" col="col-md-6" />
            <x-form.input name="location" label="Location" :value="$clinic->location" col="col-md-6" placeholder="e.g. Block A, Room 3" />
            <x-form.select name="specialty" label="Clinic type" :options="config('emr.specialty.clinic_types')" :value="$clinic->specialty ?? 'general'" col="col-md-6"
                           help="Dental, eye and physiotherapy clinics show their charting tools during the visit." />
            <x-form.textarea name="description" label="Description" :value="$clinic->description" col="col-12" rows="2" />
            <div class="col-12">
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="requires_triage" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="requires_triage" value="1" id="requires_triage" @checked(old('requires_triage', $clinic->requires_triage))>
                    <label class="form-check-label" for="requires_triage">Patients see a nurse (vitals/triage) before the doctor</label>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $clinic->is_active))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div>
    </div>
</div>
