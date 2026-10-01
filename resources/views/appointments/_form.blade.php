<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Patient <span class="text-danger">*</span></label>
                <div data-patient-picker data-url="{{ route('patients.lookup') }}"
                     data-selected-label="{{ $patient ? $patient->hospital_number.' — '.$patient->list_name : '' }}" class="position-relative">
                    <input type="hidden" name="patient_id" value="{{ old('patient_id', $appointment->patient_id) }}">
                    <input type="search" data-picker-input autocomplete="off"
                           @class(['form-control', 'is-invalid' => $errors->has('patient_id')])
                           placeholder="Type name, hospital number or phone…" aria-label="Search patient">
                    <div data-picker-results class="list-group position-absolute w-100 shadow-sm" style="z-index: 10;"></div>
                    <div data-picker-selected class="d-none"></div>
                    @error('patient_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
            <x-form.select name="clinic_id" label="Clinic" :options="$clinics->all()" :value="$appointment->clinic_id" required placeholder="Select clinic..." col="col-md-6" />
            <x-form.select name="doctor_id" label="Doctor" :options="$doctors->all()" :value="$appointment->doctor_id" placeholder="Any available doctor" col="col-md-6" />
            <x-form.input name="date" type="date" label="Date" :value="$appointment->scheduled_at?->toDateString()" required col="col-md-4" min="{{ today()->toDateString() }}" />
            <x-form.input name="time" type="time" label="Time" :value="$appointment->scheduled_at?->format('H:i')" required col="col-md-4" step="300" />
            <x-form.select name="type" label="Type" :options="\App\Models\Appointment::TYPES" :value="$appointment->type" required col="col-md-4" />
            <x-form.input name="reason" label="Reason" :value="$appointment->reason" col="col-12" placeholder="e.g. Review of blood test results" />
            <x-form.textarea name="notes" label="Notes" :value="$appointment->notes" col="col-12" rows="2" />
        </div>
    </div>
</div>
