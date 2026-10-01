@extends('layouts.app')

@section('title', 'Theatre List')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('theatre.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-light" title="Previous day"><i class="bi bi-chevron-left"></i></a>
        <form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()" aria-label="Date"></form>
        <a href="{{ route('theatre.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-light" title="Next day"><i class="bi bi-chevron-right"></i></a>
        @unless ($date->isToday())<a href="{{ route('theatre.index') }}" class="btn btn-sm btn-outline-secondary">Today</a>@endunless
        <h2 class="h5 mb-0 ms-2">{{ $date->format('l') }}, {{ format_date($date) }}</h2>
    </div>
    <span class="small text-muted">Book an operation from the patient's folder or inpatient chart.</span>
</div>

@if ($inTheatre->isNotEmpty())
    <div class="alert alert-warning d-flex flex-wrap gap-3 align-items-center">
        <strong><i class="bi bi-activity me-1"></i> In theatre now:</strong>
        @foreach ($inTheatre as $s)
            <a href="{{ route('theatre.show', $s) }}" class="alert-link">{{ $s->theatre->name }} — {{ $s->patient->list_name }} ({{ $s->procedure_name }}, since {{ $s->in_theatre_at->format('H:i') }})</a>
        @endforeach
    </div>
@endif

@if ($theatres->isEmpty())
    <div class="alert alert-info">No theatres set up. @can('catalog.manage')<a href="{{ route('admin.catalogs.index', 'theatres') }}">Add one</a>.@endcan</div>
@endif

<div class="row g-3">
    @foreach ($theatres as $theatre)
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="bi bi-door-closed me-1"></i> {{ $theatre->name }}</span>
                    <span class="small text-muted fw-normal">{{ ($surgeries[$theatre->id] ?? collect())->whereNotIn('status', ['cancelled', 'postponed'])->count() }} case(s)</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($surgeries[$theatre->id] ?? [] as $s)
                        <a href="{{ route('theatre.show', $s) }}" @class(['list-group-item list-group-item-action', 'text-muted' => in_array($s->status, ['cancelled', 'postponed'])])>
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <span class="fw-semibold">{{ $s->scheduled_at->format('H:i') }}–{{ $s->endsAt()->format('H:i') }}</span>
                                    @if ($s->urgency !== 'elective')<span class="badge text-bg-{{ \App\Models\Surgery::URGENCY[$s->urgency]['color'] }}">{{ \App\Models\Surgery::URGENCY[$s->urgency]['label'] }}</span>@endif
                                    <div class="fw-semibold">{{ $s->procedure_name }}</div>
                                    <div class="small">{{ $s->patient->list_name }} · {{ $s->patient->hospital_number }} · {{ ucfirst($s->patient->gender) }}, {{ $s->patient->age }}</div>
                                    <div class="small text-muted">Surgeon: {{ $s->surgeon?->name ?? '—' }} · Anaesthetist: {{ $s->anaesthetist?->name ?? '—' }}</div>
                                </div>
                                <span class="badge text-bg-{{ $s->statusColor() }}">{{ $s->statusLabel() }}</span>
                            </div>
                        </a>
                    @empty
                        <li class="list-group-item text-muted small">No operations booked.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endforeach
</div>
@endsection
