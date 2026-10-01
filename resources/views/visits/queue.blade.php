@extends('layouts.app')

@section('title', 'Clinic Queue')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        <li class="nav-item">
            <a @class(['nav-link py-1', 'active' => ! $clinicId]) href="{{ route('queue.index', ['mine' => $mine ?: null]) }}">
                All clinics <span class="badge text-bg-light ms-1">{{ $openCounts->sum() }}</span>
            </a>
        </li>
        @foreach ($clinics as $clinic)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $clinicId === $clinic->id]) href="{{ route('queue.index', ['clinic_id' => $clinic->id, 'mine' => $mine ?: null]) }}">
                    {{ $clinic->name }} <span class="badge text-bg-light ms-1">{{ $openCounts[$clinic->id] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="d-flex gap-2 align-items-center">
        @role('Doctor')
            <a href="{{ route('queue.index', ['clinic_id' => $clinicId, 'mine' => $mine ? null : 1]) }}" @class(['btn btn-sm', 'btn-primary' => $mine, 'btn-outline-primary' => ! $mine])>
                <i class="bi bi-person-check me-1"></i> My patients
            </a>
        @endrole
        <small class="text-muted"><i class="bi bi-arrow-repeat"></i> Auto-refreshes</small>
    </div>
</div>

@if ($clinics->isEmpty())
    <div class="alert alert-info">
        No clinics have been set up yet.
        @can('clinics.manage')<a href="{{ route('admin.clinics.create') }}">Create a clinic</a> to start checking patients in.@endcan
    </div>
@endif

<div id="queue-board" data-autorefresh="30" data-url="{{ route('queue.index', ['clinic_id' => $clinicId, 'mine' => $mine ?: null, 'partial' => 1]) }}">
    @include('visits._board')
</div>
@endsection
