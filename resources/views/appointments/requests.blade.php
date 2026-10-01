@extends('layouts.app')

@section('title', 'Online appointment requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('appointments.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Appointments</a>
    <span class="small text-muted">Requests made by patients on the portal. Confirm a time, or cancel with a reason.</span>
</div>

@error('time')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Requested</th><th>Patient</th><th>Clinic</th><th>Reason</th><th style="min-width: 26rem;">Confirm</th></tr>
            </thead>
            <tbody>
                @forelse ($requests as $appt)
                    <tr>
                        <td class="ps-3 small">
                            <strong>{{ format_date($appt->scheduled_at) }}</strong>
                            <span class="d-block text-muted">{{ $appt->notes }}</span>
                            <span class="d-block text-muted">sent {{ $appt->created_at->diffForHumans() }}</span>
                        </td>
                        <td>
                            <a href="{{ route('patients.show', $appt->patient) }}">{{ $appt->patient->list_name }}</a>
                            <small class="d-block text-muted">{{ $appt->patient->hospital_number }} · {{ $appt->patient->phone }}</small>
                        </td>
                        <td class="small">{{ $appt->clinic->name }}</td>
                        <td class="small">{{ $appt->reason }}</td>
                        <td>
                            <form method="POST" action="{{ route('appointments.confirm', $appt) }}" class="d-flex flex-wrap gap-1">
                                @csrf @method('PATCH')
                                <input type="date" name="date" value="{{ $appt->scheduled_at->toDateString() }}" min="{{ today()->toDateString() }}" required class="form-control form-control-sm w-auto" aria-label="Date">
                                <input type="time" name="time" value="{{ $appt->scheduled_at->format('H:i') }}" required class="form-control form-control-sm w-auto" aria-label="Time">
                                <select name="doctor_id" class="form-select form-select-sm w-auto" aria-label="Doctor">
                                    <option value="">Any doctor</option>
                                    @foreach ($doctors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                                <button class="btn btn-sm btn-success">Confirm</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"
                                        data-action="{{ route('appointments.cancel', $appt) }}">Cancel</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No online requests waiting.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="cancelForm">
            @csrf @method('PATCH')
            <div class="modal-header"><h5 class="modal-title" id="cancelModalLabel">Cancel request</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label for="cancel_reason" class="form-label">Reason (the patient sees this)</label>
                <input type="text" id="cancel_reason" name="cancel_reason" class="form-control" required maxlength="255" placeholder="e.g. Clinic not running that week — please call us">
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Cancel request</button></div>
        </form>
    </div>
</div>
<script>
    document.getElementById('cancelModal').addEventListener('show.bs.modal', (e) => {
        document.getElementById('cancelForm').action = e.relatedTarget.dataset.action;
    });
</script>
@endsection
