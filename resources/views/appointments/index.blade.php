@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('appointments.index', array_merge($filters, ['date' => $date->copy()->subDay()->toDateString()])) }}" class="btn btn-light" title="Previous day"><i class="bi bi-chevron-left"></i></a>
        <form method="GET" class="d-flex gap-2">
            @foreach ($filters as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()" aria-label="Date">
        </form>
        <a href="{{ route('appointments.index', array_merge($filters, ['date' => $date->copy()->addDay()->toDateString()])) }}" class="btn btn-light" title="Next day"><i class="bi bi-chevron-right"></i></a>
        @unless ($date->isToday())
            <a href="{{ route('appointments.index', $filters) }}" class="btn btn-outline-secondary btn-sm">Today</a>
        @endunless
        <h2 class="h5 mb-0 ms-2">{{ $date->format('l') }}, {{ format_date($date) }}</h2>
    </div>
    @can('appointments.manage')
        <div class="d-flex gap-2">
            @php $pendingRequests = \App\Models\Appointment::where('status', 'requested')->count(); @endphp
            @if ($pendingRequests)
                <a href="{{ route('appointments.requests') }}" class="btn btn-warning"><i class="bi bi-globe me-1"></i> Online requests <span class="badge text-bg-light">{{ $pendingRequests }}</span></a>
            @endif
            <a href="{{ route('appointments.create', ['date' => $date->isPast() && ! $date->isToday() ? null : $date->toDateString()]) }}" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i> Book appointment</a>
        </div>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <x-form.select name="clinic_id" label="Clinic" :options="$clinics->all()" :value="$filters['clinic_id'] ?? ''" placeholder="All clinics" col="col-md-4" onchange="this.form.submit()" />
            <x-form.select name="doctor_id" label="Doctor" :options="$doctors->all()" :value="$filters['doctor_id'] ?? ''" placeholder="All doctors" col="col-md-4" onchange="this.form.submit()" />
            <div class="col-md-4 d-flex flex-wrap gap-1">
                @foreach (\App\Models\Appointment::STATUSES as $key => $s)
                    <a href="{{ route('appointments.index', array_merge($filters, ['date' => $date->toDateString(), 'status' => ($filters['status'] ?? null) === $key ? null : $key])) }}"
                       @class(['badge text-decoration-none', 'text-bg-'.$s['color'] => ($filters['status'] ?? null) === $key, 'text-bg-light border' => ($filters['status'] ?? null) !== $key])>
                        {{ $s['label'] }} {{ $counts[$key] ?? 0 }}
                    </a>
                @endforeach
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Time</th><th>Patient</th><th>Clinic</th><th>Doctor</th><th>Type / Reason</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appt)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ $appt->scheduled_at->format('h:i A') }}</td>
                        <td>
                            <a href="{{ route('patients.show', $appt->patient) }}" class="text-decoration-none">{{ $appt->patient->list_name }}</a>
                            <small class="d-block text-muted">{{ $appt->patient->hospital_number }} · {{ $appt->patient->phone }}</small>
                        </td>
                        <td>{{ $appt->clinic->name }}</td>
                        <td>{{ $appt->doctor?->name ?? 'Any' }}</td>
                        <td class="small">{{ \App\Models\Appointment::TYPES[$appt->type] ?? $appt->type }}@if ($appt->reason)<span class="d-block text-muted">{{ Str::limit($appt->reason, 50) }}</span>@endif</td>
                        <td>
                            <span class="badge text-bg-{{ $appt->statusColor() }}">{{ $appt->statusLabel() }}</span>
                            @if ($appt->status === 'cancelled' && $appt->cancel_reason)<small class="d-block text-muted">{{ $appt->cancel_reason }}</small>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($appt->isScheduled())
                                @if ($appt->scheduled_at->isToday())
                                    @can('visits.checkin')
                                        <form method="POST" action="{{ route('appointments.check-in', $appt) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-right me-1"></i>Check in</button>
                                        </form>
                                    @endcan
                                @endif
                                @can('appointments.manage')
                                    <div class="dropdown d-inline">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('appointments.edit', $appt) }}"><i class="bi bi-calendar-event me-2"></i>Reschedule / edit</a></li>
                                            <li>
                                                <form method="POST" action="{{ route('appointments.no-show', $appt) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="dropdown-item"><i class="bi bi-person-x me-2"></i>Mark no-show</button>
                                                </form>
                                            </li>
                                            <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"
                                                        data-action="{{ route('appointments.cancel', $appt) }}" data-name="{{ $appt->patient->full_name }}"><i class="bi bi-x-circle me-2"></i>Cancel</button></li>
                                        </ul>
                                    </div>
                                @endcan
                            @elseif ($appt->visit)
                                <span class="small text-muted">{{ $appt->visit->queue_number }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-calendar3 fs-2 d-block mb-2"></i>No appointments on this day.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Cancel appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Cancel the appointment for <strong data-name></strong>?</p>
                <label for="cancel_reason" class="form-label">Reason</label>
                <input type="text" id="cancel_reason" name="cancel_reason" class="form-control" required maxlength="255" placeholder="e.g. Patient requested, doctor unavailable">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep</button>
                <button class="btn btn-danger">Cancel appointment</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('cancelModal').addEventListener('show.bs.modal', (e) => {
        const form = e.target.querySelector('form');
        form.action = e.relatedTarget.dataset.action;
        form.querySelector('[data-name]').textContent = e.relatedTarget.dataset.name;
    });
</script>
@endsection
