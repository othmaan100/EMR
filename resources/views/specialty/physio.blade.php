@extends('layouts.app')

@section('title', 'Physiotherapy — '.$patient->hospital_number)

@section('content')
@include('patients._mini-banner')

<div class="row g-3">
    <div class="col-xl-5 order-xl-2">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-walking me-1"></i> Courses of physiotherapy</div>
            <ul class="list-group list-group-flush">
                @forelse ($episodes as $e)
                    <a href="{{ route('specialty.physio.show', $e) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">{{ $e->region }}</span>
                            <span class="badge text-bg-{{ $e->isActive() ? 'primary' : 'secondary' }}">{{ $e->isActive() ? 'Active' : 'Discharged' }}</span>
                        </div>
                        <div class="small text-muted">Started {{ format_date($e->created_at) }} · {{ $e->sessions_count }}{{ $e->sessions_planned ? ' of '.$e->sessions_planned : '' }} sessions
                            @if ($e->outcome) · {{ $e->outcomeLabel() }}@endif</div>
                    </a>
                @empty
                    <li class="list-group-item text-muted small">No physiotherapy yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-7 order-xl-1">
        @can('physio.record')
            @unless ($patient->is_deceased)
                <form method="POST" action="{{ route('specialty.physio.store', $patient) }}" class="card">
                    @csrf
                    <div class="card-header">New assessment</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <x-form.input name="region" label="Body region / condition" required col="col-md-8" maxlength="100" placeholder="e.g. Lower back, left knee, post-stroke" />
                            <x-form.input name="pain_initial" type="number" label="Pain score (0–10)" col="col-md-4" min="0" max="10" />
                            <x-form.textarea name="complaint" label="Presenting complaint & history" required col="col-12" rows="2" />
                            <x-form.textarea name="assessment" label="Objective assessment" col="col-12" rows="3" help="Posture, range of motion, strength (MRC 0–5), special tests, function." />
                            <x-form.textarea name="goals" label="Goals" col="col-md-6" rows="2" />
                            <x-form.textarea name="plan" label="Treatment plan" col="col-md-6" rows="2" />
                            <x-form.input name="sessions_planned" type="number" label="Sessions planned" col="col-md-4" min="1" max="60" />
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Save assessment</button></div>
                </form>
            @endunless
        @endcan
    </div>
</div>
@endsection
