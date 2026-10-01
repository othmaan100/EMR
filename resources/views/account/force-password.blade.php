@extends('layouts.guest')

@section('title', 'Change password')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="w-100" style="max-width: 440px;">
        <div class="text-center mb-4">
            <span class="stat-icon bg-warning-subtle text-warning mb-3"><i class="bi bi-shield-lock"></i></span>
            <h1 class="h4 mb-1">Set your own password</h1>
            <p class="text-muted mb-0">Hello {{ strtok(auth()->user()->name, ' ') }}, you're using a temporary password. Choose a new one to continue.</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                @include('layouts.partials.flash')
                @include('account._password-form', ['block' => true])
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
            @csrf
            <button class="btn btn-link btn-sm text-muted">Sign out</button>
        </form>
    </div>
</div>
@endsection
