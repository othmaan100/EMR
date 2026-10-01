@extends('layouts.app')

@section('title', $drug->label)

@section('content')
<a href="{{ route('inventory.index') }}" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left me-1"></i> Inventory</a>

<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Usable stock</div>
            <div class="h3 mb-0 {{ $drug->reorder_level && $stock <= $drug->reorder_level ? 'text-danger' : '' }}">{{ number_format($stock) }} <small class="fs-6 text-muted">{{ $drug->unit }}</small></div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Reorder level</div>
            <div class="h3 mb-0">{{ $drug->reorder_level ?: '—' }}</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Batches in store</div>
            <div class="h3 mb-0">{{ $batches->count() }}</div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Batches (first to expire first)</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Batch</th><th>Expiry</th><th class="text-end">On hand</th><th class="text-end">Received</th><th>Supplier / GRN</th>
                @can('inventory.manage')<th>Adjust</th>@endcan</tr></thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $batch->batch_number }}</td>
                        <td>
                            {{ format_date($batch->expiry_date) }}
                            @if ($batch->isExpired())<span class="badge text-bg-danger">Expired – do not dispense</span>
                            @elseif ($batch->expiresSoon())<span class="badge text-bg-warning">Expires soon</span>@endif
                        </td>
                        <td class="text-end">{{ number_format($batch->quantity_on_hand) }}</td>
                        <td class="text-end small text-muted">{{ number_format($batch->quantity_received) }}</td>
                        <td class="small">{{ $batch->receipt?->supplier?->name ?? '—' }}<span class="d-block text-muted">{{ $batch->receipt?->receipt_number }}</span></td>
                        @can('inventory.manage')
                            <td>
                                <form method="POST" action="{{ route('inventory.adjust', $batch) }}" class="d-flex gap-1">
                                    @csrf
                                    <select name="direction" class="form-select form-select-sm" style="width: 5rem;" aria-label="Direction"><option value="remove">−</option><option value="add">+</option></select>
                                    <input type="number" name="quantity" min="1" required class="form-control form-control-sm" style="width: 5rem;" placeholder="Qty" aria-label="Quantity">
                                    <select name="reason" class="form-select form-select-sm" aria-label="Reason">
                                        @foreach (['Stock count correction', 'Damaged / broken', 'Expired – disposed', 'Returned to supplier', 'Found / returned to stock'] as $r)
                                            <option @selected($batch->isExpired() && str_starts_with($r, 'Expired'))>{{ $r }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No stock on hand.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @error('quantity')<div class="card-footer text-danger small">{{ $message }}</div>@enderror
</div>

<div class="card">
    <div class="card-header">Stock ledger</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th class="ps-3">Date</th><th>Type</th><th>Batch</th><th class="text-end">Change</th><th>Reference / reason</th><th>By</th></tr></thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="ps-3 text-nowrap">{{ format_date($m->created_at, true) }}</td>
                        <td><span class="badge text-bg-{{ \App\Models\StockMovement::TYPES[$m->type]['color'] }}">{{ \App\Models\StockMovement::TYPES[$m->type]['label'] }}</span></td>
                        <td>{{ $m->batch?->batch_number }}</td>
                        <td class="text-end fw-semibold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td>{{ $m->reason }}</td>
                        <td>{{ $m->user?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($movements->hasPages())<div class="card-footer bg-white">{{ $movements->links() }}</div>@endif
</div>
@endsection
