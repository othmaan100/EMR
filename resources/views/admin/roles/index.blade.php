@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Roles group permissions. Staff can have more than one role.</p>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New role</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th class="text-center">Staff</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($isSuper = $role->name === config('emr.super_admin_role'))
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $role->name }}</span>
                            @if (in_array($role->name, $systemRoles, true))
                                <span class="badge text-bg-light border ms-1">Built-in</span>
                            @else
                                <span class="badge bg-brand-subtle text-brand ms-1">Custom</span>
                            @endif
                        </td>
                        <td>
                            @if ($isSuper)
                                <span class="text-success"><i class="bi bi-shield-fill-check me-1"></i>All permissions</span>
                            @else
                                {{ $role->permissions_count }} of {{ $totalPermissions }}
                            @endif
                        </td>
                        <td class="text-center">
                            @can('users.view')
                                <a href="{{ route('admin.users.index', ['role' => $role->name]) }}">{{ $role->users_count }}</a>
                            @else
                                {{ $role->users_count }}
                            @endcan
                        </td>
                        <td class="text-end text-nowrap">
                            @unless ($isSuper)
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-light" title="Edit permissions"><i class="bi bi-pencil"></i></a>
                            @endunless
                            @unless (in_array($role->name, $systemRoles, true))
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('Delete this role?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
