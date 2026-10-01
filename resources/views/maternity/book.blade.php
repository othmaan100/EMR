@extends('layouts.app')

@section('title', 'Antenatal Booking')

@section('content')
<div class="mx-auto" style="max-width: 900px;">
    @include('patients._mini-banner')
    @error('patient')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('maternity.store', $patient) }}" class="card">
        @csrf
        <div class="card-header">Booking</div>
        <div class="card-body">
            <div class="row g-3">
                <x-form.input name="lmp" type="date" label="Last menstrual period (LMP)" col="col-md-4" max="{{ today()->toDateString() }}"
                              help="EDD is worked out automatically (LMP + 280 days)." />
                <x-form.input name="edd_scan" type="date" label="or EDD from dating scan" col="col-md-4" min="{{ today()->addDay()->toDateString() }}"
                              help="Overrides the LMP date." />
                <div class="col-md-4">
                    <label class="form-label">Calculated</label>
                    <div class="form-control-plaintext" id="edd-preview">—</div>
                </div>
                <x-form.input name="gravida" type="number" label="Gravida" :value="$previous + 1" required col="col-6 col-md-3" min="1" max="30" help="Including this pregnancy" />
                <x-form.input name="parity" type="number" label="Parity" :value="0" required col="col-6 col-md-3" min="0" max="30" help="Previous births ≥ 28 wks" />
                <x-form.input name="abortions" type="number" label="Miscarriages / abortions" :value="0" required col="col-6 col-md-3" min="0" max="30" />
                <x-form.input name="living_children" type="number" label="Living children" :value="0" required col="col-6 col-md-3" min="0" max="30" />
            </div>

            <label class="form-label mt-3">Risk factors</label>
            <div class="row g-1">
                @foreach (config('emr.maternity.risk_factors') as $key => $label)
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risk_factors[]" value="{{ $key }}" id="rf-{{ $key }}"
                                   @checked(in_array($key, old('risk_factors', $patient->ageInYears() !== null && ($patient->ageInYears() < 18 || $patient->ageInYears() > 35) ? ['age_extreme'] : [])))>
                            <label class="form-check-label small" for="rf-{{ $key }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <x-form.textarea name="notes" label="Booking notes" col="mt-3" rows="2" />
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary px-4"><i class="bi bi-person-heart me-1"></i> Book ANC</button>
        </div>
    </form>
</div>
<script>
    // Live EDD / gestational-age preview.
    const lmp = document.getElementById('lmp'), scan = document.getElementById('edd_scan'), out = document.getElementById('edd-preview');
    const update = () => {
        const edd = scan.value ? new Date(scan.value) : (lmp.value ? new Date(new Date(lmp.value).getTime() + 280 * 864e5) : null);
        if (!edd) { out.textContent = '—'; return; }
        const ga = 280 - Math.round((edd - new Date(new Date().toDateString())) / 864e5);
        out.innerHTML = `EDD <strong>${edd.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}</strong> · ${Math.floor(ga / 7)}w ${ga % 7}d today`;
    };
    lmp.addEventListener('change', update);
    scan.addEventListener('change', update);
</script>
@endsection
