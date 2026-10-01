@extends('layouts.app')

@section('title', 'Discharge — '.$admission->admission_number)

@section('content')
@php
    $activeMeds = $admission->prescriptions->flatMap->items->whereNull('stopped_at')
        ->map(fn ($i) => $i->drug_name.' — '.$i->directions())->implode("\n");
@endphp
<div class="mx-auto" style="max-width: 900px;">
    @include('patients._mini-banner')

    @if ($outstanding > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i> The patient owes <strong>{{ money($outstanding) }}</strong>.
            @can('billing.view')<a href="{{ route('billing.account', $patient) }}">Open account</a>@endcan
        </div>
    @endif

    <form method="POST" action="{{ route('inpatients.discharge.store', $admission) }}" class="card">
        @csrf
        <div class="card-header">Discharge from {{ $admission->ward->name }} · bed {{ $admission->bed?->label }} · day {{ $admission->lengthOfStay() }}</div>
        <div class="card-body">
            <div class="row g-3">
                <x-form.select name="discharge_type" label="Outcome" :options="\App\Models\Admission::DISCHARGE_TYPES" value="home" required col="col-md-6" />
                <x-form.textarea name="final_diagnosis" label="Final diagnosis" :value="$admission->reason" required col="col-12" rows="2" />
                <x-form.textarea name="discharge_summary" label="Summary of hospital stay" required col="col-12" rows="6"
                                 placeholder="Presentation, key findings, treatment given, progress, condition at discharge" />
                <x-form.textarea name="discharge_medications" label="Medications on discharge" :value="$activeMeds" col="col-md-6" rows="4" />
                <x-form.textarea name="follow_up" label="Follow-up & advice" col="col-md-6" rows="4" placeholder="Clinic review date, warning signs, diet, activity" />
            </div>
            @if ($outstanding > 0)
                <div class="form-check mt-3">
                    <input type="hidden" name="balance_acknowledged" value="0">
                    <input class="form-check-input" type="checkbox" name="balance_acknowledged" value="1" id="balance_acknowledged">
                    <label class="form-check-label fw-semibold" for="balance_acknowledged">Discharge with an outstanding balance of {{ money($outstanding) }}</label>
                </div>
                @error('balance_acknowledged')<div class="text-danger small">{{ $message }}</div>@enderror
            @endif
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('inpatients.show', $admission) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-warning px-4" onclick="return confirm('Discharge this patient? The bed will be released.')"><i class="bi bi-box-arrow-right me-1"></i> Discharge</button>
        </div>
    </form>
</div>
@endsection
