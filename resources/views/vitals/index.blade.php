@extends('layouts.app')

@section('title', 'Vital Signs — '.$patient->hospital_number)

@section('content')
@include('patients._mini-banner')

<div class="d-flex justify-content-end gap-2 mb-3">
    @can('nursing_notes.create')
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#noteModal"><i class="bi bi-journal-plus me-1"></i> Add nursing note</button>
    @endcan
    @can('vitals.record')
        <a href="{{ route('vitals.create', $patient) }}" class="btn btn-primary"><i class="bi bi-heart-pulse me-1"></i> Record vitals</a>
    @endcan
</div>

@if ($charts)
    <div class="row g-3 mb-3">
        @foreach ($charts as $chart)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header small">{{ $chart['title'] }} <span class="text-muted fw-normal">({{ $chart['unit'] }})</span></div>
                    <div class="card-body" style="height: 220px;">
                        <canvas data-trend-chart="{{ json_encode($chart) }}" role="img"
                                aria-label="{{ $chart['title'] }} trend over {{ count($chart['labels']) }} readings. See the table below for values."></canvas>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="card mb-3">
    <div class="card-header">All readings</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Date / time</th><th>BP</th><th>Temp</th><th>Pulse</th><th>RR</th><th>SpO₂</th>
                    <th>Wt</th><th>BMI</th><th>Pain</th><th>Glucose</th><th>NEWS2</th><th>By</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vitals as $v)
                    @php($flags = $v->isVoided() ? [] : $v->flags())
                    @php($cell = fn ($field, $value) => $value === null ? '—' : $value)
                    @php($style = fn ($f) => match ($flags[$f] ?? null) { 'critical' => 'text-danger fw-bold', 'high' => 'text-warning-emphasis fw-semibold', 'low' => 'text-info-emphasis fw-semibold', default => '' })
                    <tr @class(['text-decoration-line-through text-muted' => $v->isVoided()])>
                        <td class="ps-3 text-nowrap">{{ format_date($v->recorded_at, true) }}</td>
                        <td class="{{ $style('systolic') ?: $style('diastolic') }}">{{ $v->bloodPressure() ?? '—' }}</td>
                        <td class="{{ $style('temperature') }}">{{ $cell('temperature', $v->temperature) }}</td>
                        <td class="{{ $style('pulse') }}">{{ $cell('pulse', $v->pulse) }}</td>
                        <td class="{{ $style('respiratory_rate') }}">{{ $cell('rr', $v->respiratory_rate) }}</td>
                        <td class="{{ $style('spo2') }}">{{ $v->spo2 !== null ? $v->spo2.($v->on_oxygen ? ' (O₂)' : '') : '—' }}</td>
                        <td>{{ $cell('weight', $v->weight) }}</td>
                        <td class="{{ $style('bmi') }}">{{ $cell('bmi', $v->bmi) }}</td>
                        <td class="{{ $style('pain_score') }}">{{ $cell('pain', $v->pain_score) }}</td>
                        <td class="{{ $style('blood_glucose') }}">{{ $cell('glucose', $v->blood_glucose) }}</td>
                        <td>
                            @if (! $v->isVoided() && ($risk = $v->news2Risk()))
                                <span class="badge text-bg-{{ $risk['color'] }}">{{ $risk['score'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $v->recorder?->name }}</td>
                        <td class="text-end pe-3 text-nowrap" style="text-decoration: none;">
                            @if ($v->isVoided())
                                <span class="badge text-bg-secondary" title="{{ $v->void_reason }} — {{ $v->voider?->name }}">Entered in error</span>
                            @elseif (auth()->id() === $v->recorded_by || auth()->user()->can('vitals.void'))
                                <button class="btn btn-sm btn-link text-danger p-0" data-bs-toggle="modal" data-bs-target="#voidModal"
                                        data-action="{{ route('vitals.void', $v) }}" title="Mark as entered in error"><i class="bi bi-x-circle"></i></button>
                            @endif
                        </td>
                    </tr>
                    @if ($v->notes && ! $v->isVoided())
                        <tr><td colspan="13" class="ps-4 pt-0 text-muted border-top-0"><i class="bi bi-chat-left-text me-1"></i>{{ $v->notes }}</td></tr>
                    @endif
                @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">No vital signs recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($vitals->hasPages())
        <div class="card-footer bg-white">{{ $vitals->links() }}</div>
    @endif
</div>

<div class="card">
    <div class="card-header">Nursing notes</div>
    <ul class="list-group list-group-flush">
        @forelse ($notes as $note)
            <li class="list-group-item">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span><span class="badge text-bg-light border">{{ \App\Models\NursingNote::TYPES[$note->type] ?? $note->type }}</span> {{ $note->author?->name }}</span>
                    <span>{{ format_date($note->created_at, true) }}</span>
                </div>
                <div style="white-space: pre-line;">{{ $note->note }}</div>
            </li>
        @empty
            <li class="list-group-item text-muted small">No nursing notes yet.</li>
        @endforelse
    </ul>
</div>

@include('vitals._modals')
@endsection
