@extends('portal.layout')

@section('title', 'Immunizations')

@section('content')
@php
    $badge = ['given' => ['Given', 'success'], 'due' => ['Due now', 'warning'], 'overdue' => ['Overdue', 'danger'], 'upcoming' => ['Upcoming', 'light border'], 'unknown' => ['—', 'light border']];
@endphp
<div class="card">
    <div class="card-header">Immunization card</div>
    @if (! $patient->date_of_birth)
        <div class="card-body small text-muted">The hospital does not have a date of birth for this record, so due dates cannot be shown.</div>
    @endif
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Vaccine</th><th>Due</th><th>Status</th><th>Given on</th></tr></thead>
            <tbody>
                @foreach ($schedule as $row)
                    <tr>
                        <td class="ps-3">{{ $row['vaccine']->label }}<span class="d-block small text-muted">{{ $row['vaccine']->ageLabel() }}</span></td>
                        <td class="small">{{ $row['due'] ? format_date($row['due']) : '—' }}</td>
                        <td><span class="badge text-bg-{{ $badge[$row['status']][1] }}">{{ $badge[$row['status']][0] }}</span></td>
                        <td class="small">{{ $row['record'] ? format_date($row['record']->given_on) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">Bring the child's vaccination card to every visit. Missed a dose? Come in — it is not too late.</div>
</div>
@endsection
