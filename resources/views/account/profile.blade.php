@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Personal details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.profile.update') }}">
                    @csrf
                    @method('PUT')
                    <x-form.input name="name" label="Full name" :value="$user->name" required />
                    <x-form.input name="email" type="email" label="Email" :value="$user->email" required />
                    <x-form.input name="phone" type="tel" label="Phone" :value="$user->phone" />
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Employment <small class="text-muted fw-normal">(managed by the administrator)</small></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Username</dt><dd class="col-sm-8">{{ $user->username }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Staff ID</dt><dd class="col-sm-8">{{ $user->staff_id ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Designation</dt><dd class="col-sm-8">{{ $user->designation ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Department</dt><dd class="col-sm-8">{{ $user->department?->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Role(s)</dt><dd class="col-sm-8">{{ $user->getRoleNames()->implode(', ') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                @include('account._password-form')
            </div>
        </div>
    </div>
</div>
@endsection
