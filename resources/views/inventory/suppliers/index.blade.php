@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i> Inventory</a>
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add supplier</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Name</th><th>Contact</th><th class="text-center">Deliveries</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($suppliers as $s)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $s->name }}</td>
                        <td class="small">{{ collect([$s->contact_person, $s->phone, $s->email])->filter()->implode(' · ') ?: '—' }}</td>
                        <td class="text-center">{{ $s->receipts_count }}</td>
                        <td><span class="badge {{ $s->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end pe-3"><a href="{{ route('suppliers.edit', $s) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No suppliers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($suppliers->hasPages())<div class="card-footer bg-white">{{ $suppliers->links() }}</div>@endif
</div>
@endsection
