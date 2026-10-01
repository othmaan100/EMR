@extends('layouts.app')

@section('title', 'Check In — '.$patient->full_name)

@section('content')
<div class="mx-auto" style="max-width: 760px;">
    <div class="card mb-3">
        <div class="card-body d-flex gap-3 align-items-center">
            @if ($patient->photo)
                <img src="{{ route('patients.photo', $patient) }}" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover">
            @else
                <span class="avatar bg-secondary" style="width:56px;height:56px">{{ $patient->initials }}</span>
            @endif
            <div>
                <div class="fw-semibold">{{ $patient->full_name }}</div>
                <div class="small text-muted">{{ $patient->hospital_number }} · {{ ucfirst($patient->gender) }} · {{ $patient->age ?? '?' }} · {{ $patient->paymentLabel() }}</div>
            </div>
        </div>
    </div>

    @if ($todaysAppointment)
        <div class="alert alert-info d-flex justify-content-between align-items-center gap-3">
            <span><i class="bi bi-calendar-check me-1"></i> This patient has an appointment today at <strong>{{ $todaysAppointment->scheduled_at->format('h:i A') }}</strong> in {{ $todaysAppointment->clinic->name }}.</span>
            <form method="POST" action="{{ route('appointments.check-in', $todaysAppointment) }}">
                @csrf
                <button class="btn btn-sm btn-primary text-nowrap">Check in for appointment</button>
            </form>
        </div>
    @endif

    @error('patient')<div class="alert alert-danger">{{ $message }}</div>@enderror

    @if ($clinics->isEmpty())
        <div class="alert alert-warning">No active clinics. An administrator must create one under Administration → Clinics.</div>
    @else
        <form method="POST" action="{{ route('visits.store', $patient) }}" class="card">
            @csrf
            <div class="card-header">Walk-in check-in</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.select name="clinic_id" label="Clinic" :options="$clinics->pluck('name', 'id')->all()" required placeholder="Select clinic..." col="col-md-6" autofocus />
                    <x-form.select name="doctor_id" label="Doctor" :options="$doctors->all()" placeholder="Any available doctor" col="col-md-6" />
                    <x-form.select name="visit_type" label="Visit type" :options="\App\Models\Visit::TYPES" value="outpatient" required col="col-md-6" />
                    <div class="col-md-6">
                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            @foreach (\App\Models\Visit::PRIORITIES as $key => $p)
                                <input type="radio" class="btn-check" name="priority" id="priority-{{ $key }}" value="{{ $key }}" @checked(old('priority', 'normal') === $key)>
                                <label class="btn btn-outline-{{ $p['color'] }} flex-fill" for="priority-{{ $key }}">{{ $p['label'] }}</label>
                            @endforeach
                        </div>
                    </div>
                    <x-form.input name="complaint" label="Reason for visit / presenting complaint" col="col-12" placeholder="e.g. Fever for 3 days" />
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary px-4"><i class="bi bi-box-arrow-in-right me-1"></i> Check in</button>
            </div>
        </form>
    @endif
</div>
@endsection
