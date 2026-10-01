@extends('layouts.app')

@section('title', 'General Store')

@section('content')
<div class="row g-3 mb-3">
    @foreach ([['Stock value', money($totalValue), 'bi-cash-stack', 'primary', null],
               ['At / below reorder level', $lowCount, 'bi-arrow-down-circle', 'danger', route('stores.index', ['filter' => 'low'])],
               ['Requisitions to issue', $openRequisitions, 'bi-clipboard-check', 'warning', route('requisitions.index', ['tab' => 'open'])]] as [$label, $value, $icon, $color, $link])
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-5 fw-semibold">@if ($link)<a href="{{ $link }}" class="text-decoration-none">{{ $value }}</a>@else{{ $value }}@endif</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search item or code" aria-label="Search"></div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm" aria-label="Category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach (\App\Models\StoreItem::CATEGORIES as $c)<option @selected(($filters['category'] ?? '') === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="filter" class="form-select form-select-sm" aria-label="Stock filter" onchange="this.form.submit()">
                    <option value="">All stock</option>
                    <option value="low" @selected(($filters['filter'] ?? '') === 'low')>Low stock</option>
                    <option value="out" @selected(($filters['filter'] ?? '') === 'out')>Out of stock</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2 justify-content-md-end">
                @can('stores.manage')
                    <a href="{{ route('stores.receive') }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-box-arrow-in-down me-1"></i> Receive</a>
                    <a href="{{ route('admin.catalogs.index', 'store-items') }}" class="btn btn-sm btn-outline-secondary text-nowrap">Items</a>
                @endcan
                @can('purchasing.manage')
                    @if ($lowCount)<a href="{{ route('purchasing.create', ['low_stock' => 1]) }}" class="btn btn-sm btn-outline-primary text-nowrap">Reorder</a>@endif
                @endcan
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Item</th><th>Category</th><th class="text-end">On hand</th><th class="text-end">Reorder at</th><th class="text-end">Avg cost</th><th class="text-end">Value</th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="ps-3"><a href="{{ route('stores.show', $item) }}" class="text-decoration-none fw-semibold">{{ $item->name }}</a> <span class="small text-muted">{{ $item->code }}</span></td>
                        <td class="small">{{ $item->category }}</td>
                        <td class="text-end">
                            <span @class(['fw-semibold', 'text-danger' => $item->isLow() || $item->quantity_on_hand <= 0])>{{ number_format($item->quantity_on_hand) }}</span>
                            <span class="small text-muted">{{ $item->unit }}</span>
                            @if ($item->isLow())<span class="badge text-bg-danger ms-1">Low</span>@endif
                        </td>
                        <td class="text-end small">{{ $item->reorder_level ?: '—' }}</td>
                        <td class="text-end small">{{ money($item->average_cost) }}</td>
                        <td class="text-end">{{ money($item->value()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No store items.
                        @can('stores.manage') <a href="{{ route('admin.catalogs.create', 'store-items') }}">Add the first item</a>.@endcan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->hasPages())<div class="card-footer bg-white">{{ $items->links() }}</div>@endif
</div>
@endsection
