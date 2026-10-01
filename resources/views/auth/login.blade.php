@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="w-100" style="max-width: 420px;">
        <div class="text-center mb-4">
            @if (setting('logo'))
                <img src="{{ asset(setting('logo')) }}" alt="{{ setting('hospital_name') }}" style="max-height: 90px; max-width: 200px;" class="mb-3">
            @else
                <span class="stat-icon bg-brand text-white mb-3"><i class="bi bi-hospital"></i></span>
            @endif
            <h1 class="h4 mb-1">{{ setting('hospital_name') }}</h1>
            @if (setting('motto'))<p class="text-muted fst-italic mb-0">{{ setting('motto') }}</p>@endif
        </div>

        <div class="card">
            <div class="card-body p-4">
                <h2 class="h6 text-muted mb-3">Sign in to your account</h2>
                @include('layouts.partials.flash')

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <x-form.input name="login" label="Username or email" required autofocus autocomplete="username" />
                    <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">Keep me signed in</label>
                    </div>
                    <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">Forgot your password? Contact the system administrator.</p>
    </div>
</div>
@endsection
