@extends('layouts.app')

@section('title', 'Inpatients')

@section('content')
@php
    $allBeds = $wards->flatMap->beds;
    $counts = $allBeds->countBy('status');
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        <li class="nav-item"><a @class(['nav-link py-1', 'active' => ! $wardId]) href="{{ route('inpatients.index') }}">All wards</a></li>
        @foreach ($wards as $w)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $wardId === $w->id]) href="{{ route('inpatients.index', ['ward_id' => $w->id]) }}">
                    {{ $w->name }} <span class="badge text-bg-light ms-1">{{ $w->beds->where('status', 'occupied')->count() }}/{{ $w->beds->count() }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="small d-flex flex-wrap gap-2">
        @foreach (\App\Models\Bed::STATUSES as $key => $s)
            <span class="badge text-bg-{{ $s['color'] }}"><i class="bi {{ $s['icon'] }}"></i> {{ $s['label'] }}: {{ $counts[$key] ?? 0 }}</span>
        @endforeach
    </div>
</div>

@if ($wards->isEmpty())
    <div class="alert alert-info">No wards set up yet. @can('wards.manage')<a href="{{ route('admin.wards.create') }}">Add a ward</a>.@endcan</div>
@endif

@foreach ($shown as $ward)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <span>{{ $ward->name }} <small class="text-muted fw-normal">{{ $ward->type }}</small></span>
            <small class="text-muted fw-normal">{{ $ward->beds->where('status', 'available')->count() }} free</small>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @foreach ($ward->beds as $bed)
                    @php
                        $s = \App\Models\Bed::STATUSES[$bed->status];
                        $a = $bed->currentAdmission;
                    @endphp
                    <div class="col-6 col-md-4 col-lg-3 col-xxl-2">
                        @if ($a)
                            <a href="{{ route('inpatients.show', $a) }}" class="d-block border border-2 border-{{ $s['color'] }} rounded p-2 h-100 text-decoration-none text-body">
                                <div class="d-flex justify-content-between"><strong>{{ $bed->label }}</strong><span class="small text-muted">Day {{ $a->lengthOfStay() }}</span></div>
                                <div class="small fw-semibold text-truncate">{{ $a->patient->list_name }}</div>
                                <div class="small text-muted">{{ ucfirst($a->patient->gender) }} · {{ $a->patient->age ?? '?' }}</div>
                            </a>
                        @else
                            <div class="border rounded p-2 h-100 bg-{{ $s['color'] }}-subtle">
                                <div class="d-flex justify-content-between"><strong>{{ $bed->label }}</strong><i class="bi {{ $s['icon'] }} text-{{ $s['color'] }}"></i></div>
                                <div class="small text-muted">{{ $s['label'] }}</div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforeach

<div class="card">
    <div class="card-header">Current admissions ({{ $admissions->count() }})</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Admission</th><th>Patient</th><th>Ward / bed</th><th>Doctor</th><th>Admitted</th><th class="text-center">Day</th></tr></thead>
            <tbody>
                @forelse ($admissions as $a)
                    <tr data-href="{{ route('inpatients.show', $a) }}">
                        <td class="ps-3 fw-semibold">{{ $a->admission_number }}</td>
                        <td>{{ $a->patient->list_name }}<small class="d-block text-muted">{{ $a->patient->hospital_number }}</small></td>
                        <td>{{ $a->ward->name }} · {{ $a->bed?->label }}</td>
                        <td class="small">{{ $a->doctor?->name ?? '—' }}</td>
                        <td class="small">{{ format_date($a->admitted_at, true) }}</td>
                        <td class="text-center">{{ $a->lengthOfStay() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No patients admitted.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
