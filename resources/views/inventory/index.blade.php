@extends('layouts.app')

@section('title', 'Drug Inventory')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item"><a @class(['nav-link py-1', 'active' => ! $filter]) href="{{ route('inventory.index') }}">All drugs</a></li>
        <li class="nav-item">
            <a @class(['nav-link py-1', 'active' => $filter === 'low']) href="{{ route('inventory.index', ['filter' => 'low']) }}">
                <i class="bi bi-arrow-down-circle me-1"></i>Low stock <span class="badge text-bg-{{ $lowCount ? 'danger' : 'light' }} ms-1">{{ $lowCount }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a @class(['nav-link py-1', 'active' => $filter === 'expiring']) href="{{ route('inventory.index', ['filter' => 'expiring']) }}">
                <i class="bi bi-calendar-x me-1"></i>Expiring ≤ {{ \App\Models\Drug::EXPIRY_WARNING_DAYS }} days <span class="badge text-bg-{{ $expiringCount ? 'warning' : 'light' }} ms-1">{{ $expiringCount }}</span>
            </a>
        </li>
    </ul>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            @if ($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search drug…" aria-label="Search">
        </form>
        @can('inventory.manage')
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary text-nowrap"><i class="bi bi-truck me-1"></i> Suppliers</a>
            <a href="{{ route('inventory.receive') }}" class="btn btn-primary text-nowrap"><i class="bi bi-box-arrow-in-down me-1"></i> Receive stock</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Drug</th><th class="text-end">In stock</th><th class="text-end">Reorder level</th><th>Next expiry</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($drugs as $drug)
                    @php
                        $onHand = (int) $drug->stock_on_hand;
                        $next = $drug->next_expiry ? \Illuminate\Support\Carbon::parse($drug->next_expiry) : null;
                        $low = $drug->reorder_level > 0 && $onHand <= $drug->reorder_level;
                        $soon = $next && $next->lte(today()->addDays(\App\Models\Drug::EXPIRY_WARNING_DAYS));
                    @endphp
                    <tr data-href="{{ route('inventory.show', $drug) }}" @class(['text-muted' => ! $drug->is_active])>
                        <td class="ps-3 fw-semibold">{{ $drug->label }}</td>
                        <td class="text-end"><span @class(['fw-semibold', 'text-danger' => $low])>{{ number_format($onHand) }}</span> <small class="text-muted">{{ $drug->unit }}</small></td>
                        <td class="text-end">{{ $drug->reorder_level ?: '—' }}</td>
                        <td @class(['text-warning-emphasis fw-semibold' => $soon])>{{ $next ? format_date($next) : '—' }}</td>
                        <td>
                            @if ($low)<span class="badge text-bg-danger"><i class="bi bi-arrow-down"></i> {{ $onHand === 0 ? 'Out of stock' : 'Low' }}</span>@endif
                            @if ($soon)<span class="badge text-bg-warning"><i class="bi bi-calendar-x"></i> Expiring</span>@endif
                            @unless ($drug->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No drugs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($drugs->hasPages())<div class="card-footer bg-white">{{ $drugs->links() }}</div>@endif
</div>
@endsection
