@extends('layouts.guest')

@section('title', 'Activate patient portal')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="w-100" style="max-width: 440px;">
        <div class="text-center mb-4">
            @if (setting('logo'))
                <img src="{{ asset(setting('logo')) }}" alt="{{ setting('hospital_name') }}" style="max-height: 80px; max-width: 200px;" class="mb-3">
            @endif
            <h1 class="h4 mb-1">{{ setting('hospital_name') }}</h1>
            <p class="text-muted mb-0">Activate your patient portal</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                @include('layouts.partials.flash')
                <form method="POST" action="{{ route('portal.activate') }}">
                    @csrf
                    <x-form.input name="hospital_number" label="Hospital number" required autofocus autocomplete="username" />
                    <x-form.input name="code" label="Activation code" required autocomplete="one-time-code" placeholder="XXXX-XXXX" help="From the letter or SMS the hospital gave you." />
                    <x-form.input name="password" type="password" label="Choose a password" required autocomplete="new-password" help="At least 8 characters, with letters and numbers." />
                    <x-form.input name="password_confirmation" type="password" label="Repeat the password" required autocomplete="new-password" />
                    <button class="btn btn-primary w-100 py-2">Activate &amp; sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center small mt-3"><a href="{{ route('portal.login') }}">Already activated? Sign in</a></p>
    </div>
</div>
@endsection
