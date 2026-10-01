@extends('portal.layout')

@section('title', 'My details')

@section('content')
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Details held by the hospital</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Name</dt><dd class="col-7">{{ $patient->full_name }}</dd>
                    <dt class="col-5">Hospital no.</dt><dd class="col-7">{{ $patient->hospital_number }}</dd>
                    <dt class="col-5">Date of birth</dt><dd class="col-7">{{ $patient->date_of_birth ? format_date($patient->date_of_birth) : '—' }}</dd>
                    <dt class="col-5">Sex</dt><dd class="col-7">{{ ucfirst((string) $patient->gender) }}</dd>
                    <dt class="col-5">Phone</dt><dd class="col-7">{{ $patient->phone ?? '—' }}</dd>
                    <dt class="col-5">Address</dt><dd class="col-7">{{ $patient->address ?? '—' }}</dd>
                    <dt class="col-5">Payment</dt><dd class="col-7">{{ $patient->paymentLabel() }}</dd>
                    <dt class="col-5">Allergies</dt><dd class="col-7">{{ $patient->allergies ?: 'None recorded' }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-white small text-muted">Something wrong? Tell the records desk on your next visit.</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.password') }}">
                    @csrf @method('PUT')
                    <x-form.input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" label="New password" required autocomplete="new-password" help="At least 8 characters, with letters and numbers." />
                    <x-form.input name="password_confirmation" type="password" label="Repeat new password" required autocomplete="new-password" />
                    <button class="btn btn-primary">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
