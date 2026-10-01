@php
    $lists = config('emr.patient');
    $opt = fn (array $values) => array_combine($values, $values);
    $photoUrl = $patient->photo ? route('patients.photo', $patient) : null;
@endphp

@if (session('duplicates'))
    <div class="alert alert-warning">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-1"></i> Possible duplicate record</h6>
        <p class="small mb-2">These existing patients have the same name and date of birth, or the same phone number. Please check before registering a new record.</p>
        <ul class="list-unstyled small mb-3">
            @foreach (session('duplicates') as $dup)
                <li class="mb-1">
                    <a href="{{ $dup['url'] }}" target="_blank" class="fw-semibold">{{ $dup['hospital_number'] }}</a>
                    — {{ $dup['name'] }} · DOB {{ $dup['dob'] ?: '—' }} · {{ $dup['phone'] ?: 'no phone' }}
                </li>
            @endforeach
        </ul>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="confirm_duplicate">
            <label class="form-check-label fw-semibold" for="confirm_duplicate">This is a different person — register anyway</label>
        </div>
    </div>
@endif

<div class="row g-3">
    {{-- Photo --}}
    <div class="col-lg-3 order-lg-2">
        <div class="card mb-3">
            <div class="card-header">Photo</div>
            <div class="card-body text-center">
                <img id="photoPreview" src="{{ $photoUrl }}" alt="Patient photo" @class(['rounded mb-3 border', 'd-none' => ! $photoUrl])
                     style="width: 160px; height: 160px; object-fit: cover;">
                <div data-webcam="#photoPreview">
                    <video autoplay playsinline class="d-none w-100 rounded mb-2"></video>
                    <canvas class="d-none"></canvas>
                    <input type="hidden" name="photo_data">
                    <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" data-webcam-start><i class="bi bi-camera me-1"></i> Take photo</button>
                    <button type="button" class="btn btn-sm btn-primary w-100 mb-2 d-none" data-webcam-capture><i class="bi bi-camera-fill me-1"></i> Capture</button>
                    <button type="button" class="btn btn-sm btn-light w-100 mb-2 d-none" data-webcam-cancel>Cancel</button>
                    <div class="small text-muted mb-2" data-webcam-status></div>
                </div>
                <label for="photo" class="form-label small text-muted mb-1">or upload</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photoPreview"
                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has('photo')])>
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($photoUrl)
                    <div class="form-check mt-2 text-start">
                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                        <label class="form-check-label small" for="remove_photo">Remove photo</label>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-9 order-lg-1">
        {{-- Identity --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                Personal information
                @if ($patient->exists)<span class="badge bg-brand fs-6">{{ $patient->hospital_number }}</span>@endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.select name="title" label="Title" :options="$opt($lists['titles'])" :value="$patient->title" placeholder="—" col="col-md-2" />
                    <x-form.input name="first_name" label="First name" :value="$patient->first_name" required col="col-md-4" autofocus />
                    <x-form.input name="middle_name" label="Middle name" :value="$patient->middle_name" col="col-md-3" />
                    <x-form.input name="last_name" label="Surname" :value="$patient->last_name" required col="col-md-3" />

                    <x-form.select name="gender" label="Sex" :options="$lists['genders']" :value="$patient->gender" required placeholder="Select..." col="col-md-3" />
                    <x-form.input name="date_of_birth" type="date" label="Date of birth" :value="$patient->date_of_birth?->format('Y-m-d')" col="col-md-3" max="{{ now()->format('Y-m-d') }}" />
                    <x-form.input name="age_years" type="number" label="or Age (years)" col="col-md-2" min="0" max="130" help="If DOB unknown" />
                    <x-form.select name="marital_status" label="Marital status" :options="$opt($lists['marital_statuses'])" :value="$patient->marital_status" placeholder="—" col="col-md-4" />

                    @if (app(\App\Integrations\Identity\NinVerifier::class)->enabled())
                        <div class="col-md-4">
                            <label for="national_id" class="form-label">National ID number (NIN)
                                @if ($patient->nin_verified_at)<span class="badge text-bg-success ms-1">Verified</span>@endif</label>
                            <div class="input-group">
                                <input type="text" id="national_id" name="national_id" value="{{ old('national_id', $patient->national_id) }}" inputmode="numeric" maxlength="20"
                                       @class(['form-control', 'is-invalid' => $errors->has('national_id')])>
                                <button type="button" class="btn btn-outline-secondary" id="nin-verify" data-url="{{ route('patients.nin') }}">Verify</button>
                                @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text" id="nin-status">Ask the patient's consent before verifying.</div>
                        </div>
                        <script>
                            document.getElementById('nin-verify').addEventListener('click', async (e) => {
                                const btn = e.currentTarget, status = document.getElementById('nin-status');
                                const nin = document.getElementById('national_id').value;
                                btn.disabled = true; status.textContent = 'Checking…'; status.className = 'form-text';
                                try {
                                    const res = await fetch(btn.dataset.url, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                        body: JSON.stringify({ national_id: nin }),
                                    });
                                    const data = await res.json();
                                    if (!res.ok) throw new Error(data.errors?.national_id?.[0] || data.message || 'Lookup failed');
                                    // Fill only empty fields; show differences instead of overwriting.
                                    const diffs = [];
                                    for (const [field, value] of Object.entries({ first_name: data.first_name, middle_name: data.middle_name, last_name: data.last_name, date_of_birth: data.date_of_birth, gender: data.gender, phone: data.phone })) {
                                        const input = document.querySelector(`[name="${field}"]`);
                                        if (!input || !value) continue;
                                        if (!input.value) input.value = value;
                                        else if (input.value.trim().toLowerCase() !== String(value).toLowerCase()) diffs.push(`${field.replace('_', ' ')}: ${value}`);
                                    }
                                    status.className = 'form-text text-success';
                                    status.textContent = 'NIN verified: ' + [data.first_name, data.last_name].filter(Boolean).join(' ') + (diffs.length ? '. Differs from form — ' + diffs.join('; ') : '.');
                                } catch (err) {
                                    status.className = 'form-text text-danger';
                                    status.textContent = err.message;
                                } finally {
                                    btn.disabled = false;
                                }
                            });
                        </script>
                    @else
                        <x-form.input name="national_id" label="National ID number" :value="$patient->national_id" col="col-md-4" />
                    @endif
                    <x-form.input name="occupation" label="Occupation" :value="$patient->occupation" col="col-md-4" />
                    <x-form.select name="religion" label="Religion" :options="$opt($lists['religions'])" :value="$patient->religion" placeholder="—" col="col-md-4" />
                    <x-form.input name="nationality" label="Nationality" :value="$patient->nationality" col="col-md-4" />
                    <x-form.input name="legacy_number" label="Old folder / file number" :value="$patient->legacy_number" col="col-md-4" help="From paper records, if any." />
                    @if ($patient->exists && $patient->dob_estimated)
                        <div class="col-md-4 small text-warning align-self-center"><i class="bi bi-info-circle me-1"></i>Date of birth is estimated</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="card mb-3">
            <div class="card-header">Contact & address</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.input name="phone" type="tel" label="Phone" :value="$patient->phone" col="col-md-4" />
                    <x-form.input name="alt_phone" type="tel" label="Alternative phone" :value="$patient->alt_phone" col="col-md-4" />
                    <x-form.input name="email" type="email" label="Email" :value="$patient->email" col="col-md-4" />
                    <x-form.input name="address" label="Residential address" :value="$patient->address" col="col-12" />
                    <x-form.input name="city" label="City / Town" :value="$patient->city" col="col-md-4" />
                    <x-form.input name="state" label="State / Province" :value="$patient->state" col="col-md-4" />
                    <x-form.input name="country" label="Country" :value="$patient->country" col="col-md-4" />
                </div>
            </div>
        </div>

        {{-- Next of kin --}}
        <div class="card mb-3">
            <div class="card-header">Next of kin</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.input name="nok_name" label="Full name" :value="$patient->nok_name" col="col-md-5" />
                    <x-form.select name="nok_relationship" label="Relationship" :options="$opt($lists['relationships'])" :value="$patient->nok_relationship" placeholder="—" col="col-md-3" />
                    <x-form.input name="nok_phone" type="tel" label="Phone" :value="$patient->nok_phone" col="col-md-4" />
                    <x-form.input name="nok_address" label="Address" :value="$patient->nok_address" col="col-12" />
                </div>
            </div>
        </div>

        {{-- Medical basics --}}
        <div class="card mb-3">
            <div class="card-header">Medical basics</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.select name="blood_group" label="Blood group" :options="$opt($lists['blood_groups'])" :value="$patient->blood_group" placeholder="Unknown" col="col-md-3" />
                    <x-form.select name="genotype" label="Genotype" :options="$opt($lists['genotypes'])" :value="$patient->genotype" placeholder="Unknown" col="col-md-3" />
                    <x-form.textarea name="allergies" label="Known allergies" :value="$patient->allergies" col="col-md-6" rows="2" placeholder="e.g. Penicillin, peanuts. Leave blank if none known." />
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="card mb-3">
            <div class="card-header">Payment</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.select name="payment_type" label="Payment type" :options="\App\Models\Patient::PAYMENT_TYPES" :value="$patient->payment_type" required col="col-md-4" />
                    <div class="col-md-8" data-show-when="payment_type" data-show-values="insurance,corporate">
                        <div class="row g-3">
                            <x-form.select name="insurance_provider_id" label="Insurance / Company" :options="$providers->pluck('name', 'id')->all()" :value="$patient->insurance_provider_id" placeholder="Select..." col="col-md-6" />
                            <x-form.input name="insurance_number" label="Member / Policy no." :value="$patient->insurance_number" col="col-md-3" />
                            <x-form.input name="insurance_expiry" type="date" label="Expiry" :value="$patient->insurance_expiry?->format('Y-m-d')" col="col-md-3" />
                        </div>
                        @if ($providers->isEmpty())
                            <div class="form-text text-warning">No providers set up yet. An administrator can add them under Insurance / HMOs.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Other --}}
        <div class="card mb-3">
            <div class="card-header">Other</div>
            <div class="card-body">
                <x-form.textarea name="notes" label="Registration notes" :value="$patient->notes" rows="2" />
                @if ($patient->exists)
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_deceased" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_deceased" value="1" id="is_deceased" @checked(old('is_deceased', $patient->is_deceased))>
                        <label class="form-check-label" for="is_deceased">Patient is deceased</label>
                    </div>
                    <div class="row mt-2" data-show-when="is_deceased" data-show-values="1">
                        <x-form.input name="date_of_death" type="date" label="Date of death" :value="$patient->date_of_death?->format('Y-m-d')" col="col-md-4" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
