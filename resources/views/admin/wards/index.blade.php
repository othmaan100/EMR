@extends('layouts.app')

@section('title', 'Wards & Beds')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Set up wards, their beds and the daily bed charge (an "Accommodation" service priced in the Price List).</p>
    <a href="{{ route('admin.wards.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add ward</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Code</th><th>Ward</th><th>Type</th><th>Patients</th><th class="text-center">Beds (occupied)</th><th>Daily charge</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($wards as $ward)
                    <tr>
                        <td class="ps-3"><span class="badge text-bg-light border">{{ $ward->code }}</span></td>
                        <td class="fw-semibold">{{ $ward->name }}<small class="d-block text-muted fw-normal">{{ $ward->department?->name }}</small></td>
                        <td>{{ $ward->type }}</td>
                        <td>{{ ['any' => 'All', 'male' => 'Male only', 'female' => 'Female only'][$ward->gender] }}</td>
                        <td class="text-center">{{ $ward->beds_count }} ({{ $ward->occupied_count }})</td>
                        <td>
                            @if ($ward->bedCharge)
                                {{ $ward->bedCharge->name }}: {{ $ward->bedCharge->priceFor() !== null ? money($ward->bedCharge->priceFor()) : 'no price set' }}
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $ward->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $ward->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end pe-3"><a href="{{ route('admin.wards.edit', $ward) }}" class="btn btn-sm btn-light" title="Edit & beds"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-hospital fs-2 d-block mb-2"></i>No wards yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
