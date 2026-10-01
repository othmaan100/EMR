@extends('layouts.app')

@section('title', 'Partograph — '.$patient->hospital_number)

@section('content')
@php
    $m = config('emr.maternity');
    $active = $pregnancy->status === 'active';
    $canRecord = auth()->user()->can('maternity.record');
@endphp

@include('patients._mini-banner')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <span>
        <strong>{{ $pregnancy->obstetricFormula() }}</strong> · {{ $pregnancy->gestationLabel() }} ·
        Labour started {{ $pregnancy->labour_started_at ? format_date($pregnancy->labour_started_at, true).' ('.$pregnancy->labour_started_at->diffForHumans().')' : '—' }}
    </span>
    <span class="d-flex gap-2">
        <a href="{{ route('maternity.show', $pregnancy) }}" class="btn btn-sm btn-light">Pregnancy record</a>
        @if ($active && $canRecord)
            <a href="{{ route('maternity.delivery', $pregnancy) }}" class="btn btn-sm btn-success"><i class="bi bi-balloon-heart me-1"></i> Record delivery</a>
        @endif
    </span>
</div>

@if ($charts)
    <div class="row g-3 mb-3">
        @foreach ($charts as $chart)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header small">{{ $chart['title'] }}</div>
                    <div class="card-body" style="height: 280px;">
                        <canvas data-trend-chart="{{ json_encode($chart) }}" role="img" aria-label="{{ $chart['title'] }}. Values are listed in the table below."></canvas>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="small text-muted">Crossing the <strong>alert line</strong> means progress is slower than 1 cm/hour: reassess. Reaching the <strong>action line</strong> (4 hours later) calls for intervention by a senior clinician.</p>
@endif

@if ($active && $canRecord)
    <form method="POST" action="{{ route('maternity.partograph.store', $pregnancy) }}" class="card mb-3">
        @csrf
        <div class="card-header">Record observation (now)</div>
        <div class="card-body row g-2">
            @if ($errors->any())<div class="col-12 text-danger small">{{ $errors->first() }}</div>@endif
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_cx">Cervix (cm)</label><input type="number" step="0.5" min="0" max="10" id="p_cx" name="cervical_dilation" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_desc">Descent (/5)</label>
                <select id="p_desc" name="descent" class="form-select form-select-sm"><option value=""></option>@for ($i = 5; $i >= 0; $i--)<option value="{{ $i }}">{{ $i }}/5</option>@endfor</select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_con">Contractions /10 min</label><input type="number" min="0" max="10" id="p_con" name="contractions" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_str">Duration</label>
                <select id="p_str" name="contraction_strength" class="form-select form-select-sm"><option value=""></option><option>&lt;20s</option><option>20-40s</option><option>&gt;40s</option></select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_fhr">FHR (/min)</label><input type="number" id="p_fhr" name="fetal_heart_rate" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_liq">Liquor</label>
                <select id="p_liq" name="liquor" class="form-select form-select-sm"><option value=""></option>@foreach ($m['liquor'] as $k => $l)<option value="{{ $k }}">{{ $k }} — {{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_mould">Moulding</label>
                <select id="p_mould" name="moulding" class="form-select form-select-sm"><option value=""></option>@foreach ($m['moulding'] as $mo)<option>{{ $mo }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_pulse">Pulse</label><input type="number" id="p_pulse" name="pulse" class="form-control form-control-sm"></div>
            <div class="col-12 col-md-3"><label class="form-label small mb-1">BP</label>
                <div class="input-group input-group-sm"><input type="number" name="systolic" class="form-control" placeholder="Sys" aria-label="Systolic"><input type="number" name="diastolic" class="form-control" placeholder="Dia" aria-label="Diastolic"></div></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="p_temp">Temp °C</label><input type="number" step="0.1" id="p_temp" name="temperature" class="form-control form-control-sm"></div>
            <div class="col-12 col-md-3"><label class="form-label small mb-1" for="p_notes">Notes / drugs</label><input type="text" id="p_notes" name="notes" maxlength="255" class="form-control form-control-sm" placeholder="e.g. Oxytocin, IV fluids"></div>
            <div class="col-12 text-end"><button class="btn btn-sm btn-primary px-4">Record</button></div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-header">Observations</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th class="ps-3">Time</th><th>Cervix</th><th>Descent</th><th>Contractions</th><th>FHR</th><th>Liquor</th><th>Moulding</th><th>Pulse</th><th>BP</th><th>Temp</th><th>Notes</th><th>By</th></tr></thead>
            <tbody>
                @forelse ($pregnancy->partograph->sortByDesc('recorded_at') as $e)
                    <tr @class(['table-warning' => $e->alerts()])>
                        <td class="ps-3 text-nowrap">{{ $e->recorded_at->format('d M H:i') }}</td>
                        <td class="fw-semibold">{{ $e->cervical_dilation !== null ? $e->cervical_dilation.' cm' : '' }}</td>
                        <td>{{ $e->descent !== null ? $e->descent.'/5' : '' }}</td>
                        <td>{{ $e->contractions !== null ? $e->contractions.' · '.$e->contraction_strength : '' }}</td>
                        <td>{{ $e->fetal_heart_rate }}</td>
                        <td>{{ $e->liquor }}</td>
                        <td>{{ $e->moulding }}</td>
                        <td>{{ $e->pulse }}</td>
                        <td>{{ $e->systolic ? $e->systolic.'/'.$e->diastolic : '' }}</td>
                        <td>{{ $e->temperature }}</td>
                        <td>{{ $e->notes }} @foreach ($e->alerts() as $a)<span class="badge text-bg-warning">{{ $a }}</span>@endforeach</td>
                        <td>{{ $e->recorder?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted py-3">No observations yet. Record the first examination above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
