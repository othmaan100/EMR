@extends('layouts.app')

@section('title', 'Insurance / HMO Providers')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Health insurers, HMOs and companies that pay for their members' or employees' care.</p>
    <a href="{{ route('admin.insurance.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add provider</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Name</th><th>Type</th><th>Contact</th><th class="text-center">Patients</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($providers as $provider)
                    <tr>
                        <td><span class="badge text-bg-light border">{{ $provider->code }}</span></td>
                        <td class="fw-semibold">{{ $provider->name }}</td>
                        <td>{{ $provider->type === 'corporate' ? 'Corporate' : 'Insurance / HMO' }}</td>
                        <td class="small">{{ collect([$provider->contact_person, $provider->phone, $provider->email])->filter()->implode(' · ') ?: '—' }}</td>
                        <td class="text-center">{{ $provider->patients_count }}</td>
                        <td><span class="badge {{ $provider->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $provider->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.insurance.edit', $provider) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.insurance.destroy', $provider) }}" class="d-inline" onsubmit="return confirm('Delete this provider?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-shield-plus fs-2 d-block mb-2"></i>No providers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($providers->hasPages())
        <div class="card-footer bg-white">{{ $providers->links() }}</div>
    @endif
</div>
@endsection
