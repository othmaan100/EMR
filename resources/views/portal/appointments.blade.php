@extends('portal.layout')

@section('title', 'Appointments')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Upcoming</div>
            <ul class="list-group list-group-flush">
                @forelse ($upcoming as $a)
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ $a->scheduled_at->format('l') }}, {{ format_date($a->scheduled_at) }}
                                @if ($a->status === 'scheduled') at {{ $a->scheduled_at->format('h:i A') }}@endif</div>
                            <div class="small text-muted">{{ $a->clinic->name }} @if ($a->doctor)· {{ $a->doctor->name }}@endif</div>
                            @if ($a->status === 'requested')<span class="badge text-bg-warning">Waiting for the hospital to confirm</span>@endif
                        </div>
                        <form method="POST" action="{{ route('portal.appointments.cancel', $a) }}" onsubmit="return confirm('Cancel this appointment?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No upcoming appointments.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Past &amp; cancelled</div>
            <ul class="list-group list-group-flush">
                @forelse ($past as $a)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span>{{ format_date($a->scheduled_at) }} · {{ $a->clinic->name }}
                            @if ($a->status === 'cancelled' && $a->cancel_reason)<span class="d-block text-muted">{{ $a->cancel_reason }}</span>@endif</span>
                        <span class="badge text-bg-{{ $a->statusColor() }} align-self-start">{{ $a->statusLabel() }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Nothing yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Request an appointment</div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.appointments.request') }}">
                    @csrf
                    <x-form.select name="clinic_id" label="Clinic" :options="$clinics->all()" placeholder="Choose…" required />
                    <x-form.input name="date" type="date" label="Preferred date" required :min="today()->addDay()->toDateString()" :max="today()->addDays(90)->toDateString()" />
                    <x-form.select name="time_of_day" label="Preferred time" :options="['morning' => 'Morning', 'afternoon' => 'Afternoon']" value="morning" required />
                    <x-form.input name="reason" label="Reason for visit" required maxlength="255" placeholder="e.g. Follow-up for blood pressure" />
                    <button class="btn btn-primary w-100">Send request</button>
                </form>
                <p class="small text-muted mt-2 mb-0">The hospital will confirm the exact time{{ setting('sms_appointment_reminders') ? ' by SMS' : '' }}. For emergencies, come straight to the hospital.</p>
            </div>
        </div>
    </div>
</div>
@endsection
