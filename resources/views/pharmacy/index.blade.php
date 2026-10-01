@extends('layouts.app')

@section('title', 'Pharmacy')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach (\App\Http\Controllers\PharmacyController::TABS as $key => $t)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $tab === $key]) href="{{ route('pharmacy.index', ['tab' => $key]) }}">
                    <i class="bi {{ $t['icon'] }} me-1"></i>{{ $t['label'] }}
                    <span class="badge text-bg-light ms-1">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Rx no. or patient…" aria-label="Search" style="min-width: 240px;">
        <button class="btn btn-outline-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Prescription</th><th>Patient</th><th>Items</th><th>Prescriber</th><th>Time</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($prescriptions as $rx)
                    <tr data-href="{{ route('pharmacy.show', $rx) }}">
                        <td class="ps-3 fw-semibold">{{ $rx->prescription_number }}
                            <span class="d-block small text-muted fw-normal">{{ $rx->visit?->clinic?->name }}</span></td>
                        <td>
                            {{ $rx->patient->list_name }}
                            <small class="d-block text-muted">{{ $rx->patient->hospital_number }} · {{ $rx->patient->paymentLabel() }}</small>
                        </td>
                        <td class="small">
                            @foreach ($rx->items as $item)
                                <div @class(['text-decoration-line-through text-muted' => $item->isComplete()])>{{ $item->drug_name }}</div>
                            @endforeach
                        </td>
                        <td class="small">{{ $rx->prescriber?->name }}</td>
                        <td class="small text-nowrap">{{ ($tab === 'done' ? $rx->dispensed_at : $rx->created_at)->diffForHumans() }}</td>
                        <td class="text-end pe-3">
                            <a href="{{ route('pharmacy.show', $rx) }}" class="btn btn-sm {{ $tab === 'done' ? 'btn-outline-secondary' : 'btn-primary' }}">
                                {{ $tab === 'done' ? 'View' : 'Dispense' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($prescriptions->hasPages())<div class="card-footer bg-white">{{ $prescriptions->links() }}</div>@endif
</div>
@endsection
