@extends('layouts.app')

@section('title', 'Patients')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" autofocus
                           placeholder="Name, hospital no., phone, national ID, insurance no.">
                </div>
            </div>
            <x-form.select name="gender" label="Sex" :options="config('emr.patient.genders')" :value="$filters['gender'] ?? ''" placeholder="Any" col="col-md-2" />
            <x-form.select name="payment_type" label="Payment" :options="\App\Models\Patient::PAYMENT_TYPES" :value="$filters['payment_type'] ?? ''" placeholder="Any" col="col-md-2" />
            <x-form.select name="registered" label="Registered" :options="['today' => 'Today']" :value="$filters['registered'] ?? ''" placeholder="Any time" col="col-md-1" />
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill">Search</button>
                <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-muted small">{{ number_format($patients->total()) }} {{ Str::plural('patient', $patients->total()) }}</span>
    @can('patients.create')
        <a href="{{ route('patients.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Register patient</a>
    @endcan
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Hospital no.</th>
                    <th>Name</th>
                    <th>Sex / Age</th>
                    <th>Phone</th>
                    <th>Payment</th>
                    <th>Registered</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr data-href="{{ route('patients.show', $patient) }}">
                        <td><span class="fw-semibold text-brand">{{ $patient->hospital_number }}</span>
                            @if ($patient->legacy_number)<small class="d-block text-muted">Old: {{ $patient->legacy_number }}</small>@endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($patient->photo)
                                    <img src="{{ route('patients.photo', $patient) }}" alt="" class="rounded-circle" style="width:34px;height:34px;object-fit:cover" loading="lazy">
                                @else
                                    <span class="avatar bg-secondary">{{ $patient->initials }}</span>
                                @endif
                                <span>
                                    {{ $patient->list_name }}
                                    @if ($patient->is_deceased)<span class="badge text-bg-dark ms-1">Deceased</span>@endif
                                </span>
                            </div>
                        </td>
                        <td>{{ ucfirst($patient->gender) }} · {{ $patient->age ?? '—' }}</td>
                        <td>{{ $patient->phone ?: '—' }}</td>
                        <td class="small">{{ $patient->paymentLabel() }}</td>
                        <td class="small text-muted">{{ format_date($patient->created_at) }}</td>
                        <td class="text-end"><a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-light" title="Open folder"><i class="bi bi-folder2-open"></i></a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-person-vcard fs-2 d-block mb-2"></i>
                            @if (! empty($filters['q']))
                                No patient matches "{{ $filters['q'] }}".
                                @can('patients.create')<a href="{{ route('patients.create') }}">Register a new patient</a>@endcan
                            @else
                                No patients registered yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($patients->hasPages())
        <div class="card-footer bg-white">{{ $patients->links() }}</div>
    @endif
</div>
@endsection
