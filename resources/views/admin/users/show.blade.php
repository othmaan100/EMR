@extends('layouts.app')

@section('title', $user->name)

@section('content')
@php($canManage = auth()->user()->can('users.manage') && (! $user->isSuperAdmin() || auth()->user()->isSuperAdmin()))

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar mb-2" style="width:72px;height:72px;font-size:1.5rem">{{ $user->initials() }}</span>
                <h2 class="h5 mb-0">{{ $user->name }}</h2>
                <p class="text-muted mb-2">{{ $user->designation ?: '—' }}</p>
                <div class="mb-2">
                    @foreach ($user->roles as $role)
                        <span class="badge bg-brand-subtle text-brand">{{ $role->name }}</span>
                    @endforeach
                </div>
                <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                @if ($user->must_change_password)
                    <span class="badge text-bg-warning">Password change pending</span>
                @endif
                @if ($user->isLocked())
                    <div class="alert alert-danger small mt-2 mb-0 py-2">
                        <i class="bi bi-lock-fill me-1"></i> Locked until {{ $user->locked_until->format('h:i A') }} after repeated wrong passwords.
                        @if ($canManage)
                            <form method="POST" action="{{ route('admin.users.unlock', $user) }}" class="mt-1">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">Unlock now</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            @if ($canManage)
                <div class="card-footer bg-white d-flex flex-wrap gap-2 justify-content-center">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="bi bi-key me-1"></i> Reset password</button>
                    @unless ($user->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.users.status', $user) }}"
                              onsubmit="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ addslashes($user->name) }}?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                <i class="bi {{ $user->is_active ? 'bi-person-x' : 'bi-person-check' }} me-1"></i>{{ $user->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    @endunless
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Username</dt><dd class="col-sm-8">{{ $user->username }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Email</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Phone</dt><dd class="col-sm-8">{{ $user->phone ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Staff ID</dt><dd class="col-sm-8">{{ $user->staff_id ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Department</dt><dd class="col-sm-8">{{ $user->department?->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Account created</dt><dd class="col-sm-8">{{ format_date($user->created_at) }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Last sign-in</dt>
                    <dd class="col-sm-8">{{ $user->last_login_at ? format_date($user->last_login_at, true).' from '.$user->last_login_ip : 'Never' }}</dd>
                </dl>
            </div>
        </div>

        @can('audit.view')
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    Recent activity
                    <a href="{{ route('admin.audit.index', ['user_id' => $user->id]) }}" class="small fw-normal">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($activity as $log)
                        <li class="list-group-item d-flex justify-content-between gap-3 small">
                            <span>{{ $log->description ?? $log->event }}</span>
                            <span class="text-muted text-nowrap">{{ format_date($log->created_at, true) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        @endcan
    </div>
</div>

@if ($canManage)
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.users.password', $user) }}" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordLabel">Reset password for {{ $user->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Give this temporary password to the staff member. They will be asked to change it at their next sign-in.</p>
                    <div class="mb-3">
                        <label for="reset_password" class="form-label">New password</label>
                        <input type="password" id="reset_password" name="password" required autocomplete="new-password"
                               @class(['form-control', 'is-invalid' => $errors->resetPassword->has('password')])>
                        @if ($errors->resetPassword->has('password'))<div class="invalid-feedback">{{ $errors->resetPassword->first('password') }}</div>@endif
                    </div>
                    <div>
                        <label for="reset_password_confirmation" class="form-label">Confirm password</label>
                        <input type="password" id="reset_password_confirmation" name="password_confirmation" required class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning">Reset password</button>
                </div>
            </form>
        </div>
    </div>
    @if ($errors->resetPassword->any())
        <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal('#resetPasswordModal').show());</script>
    @endif
@endif
@endsection
