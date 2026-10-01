@extends('layouts.app')

@section('title', 'Physiotherapy — '.$episode->region)

@section('content')
@php
    $treatments = config('emr.specialty.physio_treatments');
    $canRecord = auth()->user()->can('physio.record') && $episode->isActive();
    $done = $episode->sessions->count();
@endphp

@include('patients._mini-banner')

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><a href="{{ route('specialty.physio', $patient) }}" class="text-decoration-none">Physiotherapy</a> › {{ $episode->region }}</span>
                <span class="badge text-bg-{{ $episode->isActive() ? 'primary' : 'secondary' }}">{{ $episode->isActive() ? 'Active' : 'Discharged '.format_date($episode->discharged_at) }}</span>
            </div>
            <div class="card-body small">
                <div class="row g-3 mb-2">
                    <div class="col-4"><div class="text-muted">Pain at start</div><div class="fs-5 fw-semibold">{{ $episode->pain_initial ?? '—' }}<span class="small text-muted">/10</span></div></div>
                    <div class="col-4"><div class="text-muted">Pain now</div><div class="fs-5 fw-semibold">{{ $episode->latestPain() ?? '—' }}<span class="small text-muted">/10</span></div></div>
                    <div class="col-4"><div class="text-muted">Sessions</div><div class="fs-5 fw-semibold">{{ $done }}{{ $episode->sessions_planned ? ' / '.$episode->sessions_planned : '' }}</div></div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-3 fw-normal text-muted">Complaint</dt><dd class="col-sm-9">{{ $episode->complaint }}</dd>
                    @if ($episode->assessment)<dt class="col-sm-3 fw-normal text-muted">Assessment</dt><dd class="col-sm-9" style="white-space: pre-line">{{ $episode->assessment }}</dd>@endif
                    @if ($episode->goals)<dt class="col-sm-3 fw-normal text-muted">Goals</dt><dd class="col-sm-9">{{ $episode->goals }}</dd>@endif
                    @if ($episode->plan)<dt class="col-sm-3 fw-normal text-muted">Plan</dt><dd class="col-sm-9">{{ $episode->plan }}</dd>@endif
                    @if ($episode->outcome)<dt class="col-sm-3 fw-normal text-muted">Outcome</dt><dd class="col-sm-9">{{ $episode->outcomeLabel() }} — {{ $episode->discharge_notes }}</dd>@endif
                    <dt class="col-sm-3 fw-normal text-muted">Assessed by</dt><dd class="col-sm-9">{{ $episode->creator?->name }}, {{ format_date($episode->created_at) }}</dd>
                </dl>
            </div>
        </div>

        @if ($chart)
            <div class="card mb-3">
                <div class="card-header small">Pain score by session <span class="text-muted fw-normal">(0 = none, 10 = worst)</span></div>
                <div class="card-body" style="height: 240px;">
                    <canvas data-trend-chart="{{ json_encode($chart) }}" role="img" aria-label="Pain before and after each of {{ $done }} sessions. Values are in the table below."></canvas>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">Sessions</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">#</th><th>Date</th><th>Treatment</th><th class="text-center">Pain before</th><th class="text-center">Pain after</th><th>Notes</th><th>Therapist</th></tr></thead>
                    <tbody>
                        @forelse ($episode->sessions as $i => $s)
                            <tr>
                                <td class="ps-3">{{ $i + 1 }}</td>
                                <td class="small text-nowrap">{{ format_date($s->session_date) }}</td>
                                <td class="small">{{ $s->treatmentLabels() }}</td>
                                <td class="text-center">{{ $s->pain_before ?? '—' }}</td>
                                <td class="text-center">{{ $s->pain_after ?? '—' }}</td>
                                <td class="small">{{ $s->notes }}</td>
                                <td class="small">{{ $s->therapist?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No sessions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @if ($canRecord)
            <form method="POST" action="{{ route('specialty.physio.session', $episode) }}" class="card mb-3">
                @csrf
                <div class="card-header">Record a session</div>
                <div class="card-body">
                    <x-form.input name="session_date" type="date" label="Date" :value="today()->toDateString()" required :max="today()->toDateString()" />
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Treatment given <span class="text-danger">*</span></legend>
                        @foreach ($treatments as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="treatments[]" value="{{ $key }}" id="t_{{ $key }}" @checked(in_array($key, old('treatments', [])))>
                                <label class="form-check-label small" for="t_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('treatments')<div class="text-danger small">{{ $message }}</div>@enderror
                    </fieldset>
                    <div class="row g-2">
                        <x-form.input name="pain_before" type="number" label="Pain before" col="col-6" min="0" max="10" />
                        <x-form.input name="pain_after" type="number" label="Pain after" col="col-6" min="0" max="10" />
                    </div>
                    <x-form.textarea name="notes" label="Notes / progress" rows="2" />
                    <button class="btn btn-primary w-100">Save session</button>
                    <div class="form-text">A session fee is charged if priced.</div>
                </div>
            </form>

            <form method="POST" action="{{ route('specialty.physio.discharge', $episode) }}" class="card" onsubmit="return confirm('Discharge from this course of physiotherapy?')">
                @csrf
                <div class="card-header">Discharge</div>
                <div class="card-body">
                    <x-form.select name="outcome" label="Outcome" :options="config('emr.specialty.physio_outcomes')" placeholder="Choose…" required />
                    <x-form.textarea name="discharge_notes" label="Discharge notes & home advice" rows="2" />
                    <button class="btn btn-outline-secondary w-100">Discharge</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
