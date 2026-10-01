@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ $users->total() }} staff {{ Str::plural('account', $users->total()) }}</p>
    @can('users.manage')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add staff</a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-form.input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Name, username, email, staff ID" col="col-md-4" />
            <x-form.select name="role" label="Role" :options="$roles->combine($roles)->all()" :value="$filters['role'] ?? ''" placeholder="All roles" col="col-md-3" />
            <x-form.select name="department_id" label="Department" :options="$departments->all()" :value="$filters['department_id'] ?? ''" placeholder="All departments" col="col-md-3" />
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$filters['status'] ?? ''" placeholder="Any" col="col-md-2" />
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-search me-1"></i> Search</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Staff ID</th>
                    <th>Department</th>
                    <th>Role(s)</th>
                    <th>Last sign-in</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar">{{ $user->initials() }}</span>
                                <div class="lh-sm">
                                    <a href="{{ route('admin.users.show', $user) }}" class="fw-semibold text-decoration-none">{{ $user->name }}</a>
                                    <small class="d-block text-muted">{{ $user->designation ?: '@'.$user->username }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->staff_id ?: '—' }}</td>
                        <td>{{ $user->department?->name ?? '—' }}</td>
                        <td>
                            @foreach ($user->roles as $role)
                                <span class="badge bg-brand-subtle text-brand">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td class="small text-muted">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-light" title="View"><i class="bi bi-eye"></i></a>
                            @can('users.manage')
                                @if (! $user->isSuperAdmin() || auth()->user()->isSuperAdmin())
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No staff found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-footer bg-white">{{ $users->links() }}</div>
    @endif
</div>
@endsection
