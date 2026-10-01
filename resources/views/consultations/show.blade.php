@extends('layouts.app')

@section('title', 'Consultation — '.$patient->hospital_number)

@section('content')
@include('patients._mini-banner')

@if (! $consultation)
    <div class="card">
        <div class="card-body text-center py-5">
            @if ($visit->status === \App\Models\Visit::WAITING_DOCTOR && auth()->user()->can('consultations.create') && auth()->user()->can('queue.manage'))
                <i class="bi bi-clipboard2-pulse fs-1 text-brand d-block mb-2"></i>
                <p class="mb-3">{{ $patient->first_name }} is waiting to be seen.</p>
                <form method="POST" action="{{ route('visits.move', $visit) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ \App\Models\Visit::IN_CONSULTATION }}">
                    <button class="btn btn-primary btn-lg"><i class="bi bi-play-circle me-1"></i> Start consultation</button>
                </form>
            @else
                <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
                <p class="text-muted mb-0">No consultation has been recorded for visit {{ $visit->visit_number }} ({{ $visit->statusLabel() }}).</p>
            @endif
        </div>
    </div>
@else
    <div @class(['alert d-flex flex-wrap justify-content-between align-items-center gap-2 py-2',
                 'alert-success' => $consultation->isSigned(), 'alert-warning' => ! $consultation->isSigned()])>
        <span>
            @if ($consultation->isSigned())
                <i class="bi bi-patch-check-fill me-1"></i> Signed by <strong>{{ $consultation->doctor?->name }}</strong> on {{ format_date($consultation->signed_at, true) }}
            @else
                <i class="bi bi-pencil-square me-1"></i> Draft by <strong>{{ $consultation->doctor?->name }}</strong>, started {{ $consultation->created_at->format('h:i A') }}
                @unless ($editable) — read only for you @endunless
            @endif
        </span>
        <span class="small">Visit {{ $visit->visit_number }} · {{ $visit->clinic->name }} · {{ format_date($visit->checked_in_at) }}</span>
    </div>

    @error('presenting_complaint')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('diagnosis')<div class="alert alert-danger">{{ $message }}</div>@enderror

    @if (in_array($visit->clinic->specialty, ['dental', 'eye', 'physio'], true))
        @can('specialty.view')
            @php
                $tool = [
                    'dental' => ['specialty.dental', 'Open dental chart', 'bi-emoji-smile'],
                    'eye' => ['specialty.eye', 'Open eye examination', 'bi-eye'],
                    'physio' => ['specialty.physio', 'Open physiotherapy', 'bi-person-walking'],
                ][$visit->clinic->specialty];
            @endphp
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span class="small">{{ $visit->clinic->name }} — record specialty findings alongside this consultation.</span>
                <a href="{{ route($tool[0], $patient) }}" class="btn btn-sm btn-primary"><i class="bi {{ $tool[2] }} me-1"></i> {{ $tool[1] }}</a>
            </div>
        @endcan
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            @include('consultations._notes')
            @include('consultations._diagnoses')
            @include('consultations._orders')
            @if ($editable)
                @can('admissions.manage')
                    <div class="alert alert-light border d-flex justify-content-between align-items-center">
                        <span class="small">Does this patient need to stay in hospital?</span>
                        <a href="{{ route('inpatients.create', ['patient' => $patient, 'visit' => $visit->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-hospital me-1"></i> Admit patient</a>
                    </div>
                @endcan
                @include('consultations._sign')
            @endif
            @if ($consultation->isSigned())
                @include('consultations._addenda')
            @endif
        </div>
        <div class="col-xl-4">
            @include('consultations._sidebar')
        </div>
    </div>
@endif
@endsection
