@extends('layouts.app')

@section('title', 'Clinics')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Clinics are the service points patients check in to and queue at (e.g. General OPD, Paediatrics, ANC).</p>
    <a href="{{ route('admin.clinics.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add clinic</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th>Department</th><th>Location</th><th>Triage</th><th class="text-center">Visits</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($clinics as $clinic)
                    <tr>
                        <td><span class="badge text-bg-light border">{{ $clinic->code }}</span></td>
                        <td class="fw-semibold">{{ $clinic->name }}</td>
                        <td>{{ $clinic->department?->name ?? '—' }}</td>
                        <td class="small">{{ $clinic->location ?: '—' }}</td>
                        <td>{!! $clinic->requires_triage ? '<i class="bi bi-check-lg text-success"></i> Nurse first' : '<span class="text-muted">Direct to doctor</span>' !!}</td>
                        <td class="text-center">{{ $clinic->visits_count }}</td>
                        <td><span class="badge {{ $clinic->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $clinic->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.clinics.edit', $clinic) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.clinics.destroy', $clinic) }}" class="d-inline" onsubmit="return confirm('Delete this clinic?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-door-open fs-2 d-block mb-2"></i>No clinics yet. Add your first one, e.g. "General Outpatient" (GOPD).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
