@extends('layouts.app')

@section('title', 'Departments')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name or code" aria-label="Search">
        <select name="type" class="form-select" aria-label="Type" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach (config('emr.department_types') as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.departments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add department</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Head</th>
                    <th>Location</th>
                    <th class="text-center">Staff</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td><span class="badge text-bg-light border">{{ $department->code }}</span></td>
                        <td class="fw-semibold">{{ $department->name }}</td>
                        <td>{{ $department->type }}</td>
                        <td>{{ $department->head?->name ?? '—' }}</td>
                        <td class="small">{{ $department->location ?: '—' }}{{ $department->phone_extension ? ' · Ext. '.$department->phone_extension : '' }}</td>
                        <td class="text-center">
                            @can('users.view')
                                <a href="{{ route('admin.users.index', ['department_id' => $department->id]) }}">{{ $department->users_count }}</a>
                            @else
                                {{ $department->users_count }}
                            @endcan
                        </td>
                        <td><span class="badge {{ $department->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="d-inline"
                                  onsubmit="return confirm('Delete this department?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-diagram-3 fs-2 d-block mb-2"></i>
                            No departments yet. Add your first one, e.g. Outpatient (OPD), Laboratory, Pharmacy.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($departments->hasPages())
        <div class="card-footer bg-white">{{ $departments->links() }}</div>
    @endif
</div>
@endsection
