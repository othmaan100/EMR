@extends('layouts.guest')

@section('title', 'Patient portal')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="w-100" style="max-width: 420px;">
        <div class="text-center mb-4">
            @if (setting('logo'))
                <img src="{{ asset(setting('logo')) }}" alt="{{ setting('hospital_name') }}" style="max-height: 80px; max-width: 200px;" class="mb-3">
            @endif
            <h1 class="h4 mb-1">{{ setting('hospital_name') }}</h1>
            <p class="text-muted mb-0">Patient portal</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                @include('layouts.partials.flash')
                <form method="POST" action="{{ route('portal.login') }}">
                    @csrf
                    <x-form.input name="hospital_number" label="Hospital number" required autofocus autocomplete="username" help="Printed on your hospital card, e.g. {{ setting('patient_number_prefix') }}000123" />
                    <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
                    <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center small mt-3">
            First time? <a href="{{ route('portal.activate') }}">Activate your account</a> with the code from the hospital.<br>
            <span class="text-muted">Forgot your password? Ask the records desk for a new code.</span>
        </p>
    </div>
</div>
@endsection
