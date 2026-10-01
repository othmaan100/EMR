@extends('layouts.app')

@section('title', 'Admit Patient')

@section('content')
<div class="mx-auto" style="max-width: 900px;">
    @include('patients._mini-banner', ['visit' => null])

    @if ($current)
        <div class="alert alert-warning">
            {{ $patient->first_name }} is already admitted ({{ $current->admission_number }}).
            <a href="{{ route('inpatients.show', $current) }}">Open the inpatient chart</a>.
        </div>
    @elseif ($wards->flatMap->beds->isEmpty())
        <div class="alert alert-warning">No free beds in wards that admit {{ $patient->gender }} patients.</div>
    @else
        @error('patient')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('inpatients.store', $patient) }}" class="card">
            @csrf
            @if ($visit)<input type="hidden" name="visit_id" value="{{ $visit->id }}">@endif
            <div class="card-header">Admission</div>
            <div class="card-body">
                <label class="form-label">Bed <span class="text-danger">*</span></label>
                @error('bed_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <div class="mb-3">
                    @foreach ($wards as $ward)
                        @continue($ward->beds->isEmpty())
                        <div class="mb-2">
                            <div class="small fw-semibold text-muted">{{ $ward->name }} <span class="fw-normal">({{ $ward->type }})</span></div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($ward->beds as $bed)
                                    <input type="radio" class="btn-check" name="bed_id" id="bed-{{ $bed->id }}" value="{{ $bed->id }}" @checked(old('bed_id') == $bed->id) required>
                                    <label class="btn btn-sm btn-outline-success" for="bed-{{ $bed->id }}">{{ $bed->label }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="row g-3">
                    <x-form.select name="doctor_id" label="Admitting / responsible doctor" :options="$doctors->all()"
                                   :value="auth()->user()->hasRole('Doctor') ? auth()->id() : null" placeholder="Select…" col="col-md-6" />
                    <x-form.textarea name="reason" label="Reason for admission / working diagnosis" :value="$reason" required col="col-12" rows="3" />
                </div>
                <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle me-1"></i> An admission deposit, if your hospital requires one, is collected at the cashier (patient account → Deposit).</p>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary px-4"><i class="bi bi-hospital me-1"></i> Admit</button>
            </div>
        </form>
    @endif
</div>
@endsection
