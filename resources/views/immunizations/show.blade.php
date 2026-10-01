@extends('layouts.app')

@section('title', 'Immunizations — '.$patient->hospital_number)

@section('content')
@php
    $counts = $schedule->countBy('status');
    $canRecord = auth()->user()->can('immunization.record');
    $pending = $schedule->whereIn('status', ['due', 'overdue', 'upcoming', 'unknown']);
@endphp

@include('patients._mini-banner')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-success fs-6">{{ $counts['given'] ?? 0 }} given</span>
        <span class="badge text-bg-warning fs-6">{{ $counts['due'] ?? 0 }} due</span>
        <span class="badge text-bg-danger fs-6">{{ $counts['overdue'] ?? 0 }} overdue</span>
        <span class="badge text-bg-light border fs-6">{{ $counts['upcoming'] ?? 0 }} upcoming</span>
    </div>
    <a href="{{ route('immunizations.card', $patient) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Vaccination card</a>
</div>

@unless ($patient->date_of_birth)
    <div class="alert alert-warning">This patient has no date of birth, so due dates can't be calculated. Add it on the patient record.</div>
@endunless

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Age</th><th>Vaccine</th><th>Due date</th><th>Status</th><th>Given</th></tr></thead>
                    <tbody>
                        @foreach ($schedule as $row)
                            <tr>
                                <td class="ps-3 small text-muted text-nowrap">{{ $row['vaccine']->ageLabel() }}</td>
                                <td>{{ $row['vaccine']->label }} <small class="text-muted">{{ $row['vaccine']->route }}</small></td>
                                <td class="small">{{ $row['due'] ? format_date($row['due']) : '—' }}</td>
                                <td>@include('immunizations._status', ['status' => $row['status']])</td>
                                <td class="small">
                                    @if ($row['record'])
                                        {{ format_date($row['record']->given_on) }}
                                        <span class="d-block text-muted">{{ collect([$row['record']->batch_number ? 'Batch '.$row['record']->batch_number : null, $row['record']->site, $row['record']->giver?->name])->filter()->implode(' · ') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @if ($canRecord && $pending->isNotEmpty())
            <form method="POST" action="{{ route('immunizations.store', $patient) }}" class="card">
                @csrf
                <div class="card-header">Record a dose</div>
                <div class="card-body">
                    @if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
                    <label for="vaccine_id" class="form-label">Vaccine</label>
                    <select id="vaccine_id" name="vaccine_id" class="form-select mb-3" required>
                        @foreach ($pending->sortBy(fn ($r) => ['overdue' => 0, 'due' => 1, 'upcoming' => 2, 'unknown' => 3][$r['status']]) as $row)
                            <option value="{{ $row['vaccine']->id }}">{{ $row['vaccine']->label }}{{ $row['status'] === 'overdue' ? ' (overdue)' : ($row['status'] === 'due' ? ' (due)' : '') }}</option>
                        @endforeach
                    </select>
                    <label for="given_on" class="form-label">Date given</label>
                    <input type="date" id="given_on" name="given_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control mb-3" required>
                    <div class="row g-2">
                        <div class="col-6"><label for="batch_number" class="form-label">Batch no.</label><input type="text" id="batch_number" name="batch_number" maxlength="50" class="form-control"></div>
                        <div class="col-6"><label for="site" class="form-label">Site</label>
                            <select id="site" name="site" class="form-select"><option></option><option>Left arm</option><option>Right arm</option><option>Left thigh</option><option>Right thigh</option><option>Oral</option></select></div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-shield-plus me-1"></i> Record</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
