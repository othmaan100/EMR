@extends('layouts.app')

@section('title', $item->name)

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <a href="{{ route('stores.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> General store</a>
                <h1 class="h5 mt-2 mb-1">{{ $item->name }}</h1>
                <div class="small text-muted mb-3">{{ $item->code }} · {{ $item->category }}</div>
                <div class="d-flex justify-content-between"><span class="text-muted small">On hand</span>
                    <span @class(['fw-semibold', 'text-danger' => $item->isLow()])>{{ number_format($item->quantity_on_hand) }} {{ $item->unit }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted small">Reorder level</span><span>{{ $item->reorder_level ?: '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted small">Average cost</span><span>{{ money($item->average_cost) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted small">Stock value</span><span>{{ money($item->value()) }}</span></div>
            </div>
        </div>
        @can('stores.manage')
            <form method="POST" action="{{ route('stores.adjust', $item) }}" class="card">
                @csrf
                <div class="card-header">Adjust stock</div>
                <div class="card-body">
                    <x-form.select name="type" label="Type" :options="['adjustment' => 'Stock-count correction (+/−)', 'write_off' => 'Write off (damaged / expired / lost)', 'return' => 'Returned from a department']" required />
                    <x-form.input name="quantity" type="number" label="Quantity" required help="For a correction, use a minus sign to reduce stock." />
                    <x-form.input name="reason" label="Reason" required maxlength="255" />
                    <button class="btn btn-outline-primary w-100">Save adjustment</button>
                </div>
            </form>
        @endcan
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Stock ledger</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Movement</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Department / reference</th><th>By</th></tr></thead>
                    <tbody>
                        @forelse ($movements as $m)
                            <tr>
                                <td class="ps-3 small text-nowrap">{{ format_date($m->created_at, true) }}</td>
                                <td class="small">{{ \App\Models\StoreMovement::TYPES[$m->type] ?? $m->type }}@if ($m->reason)<span class="d-block text-muted">{{ $m->reason }}</span>@endif</td>
                                <td @class(['text-end fw-semibold', 'text-success' => $m->quantity > 0, 'text-danger' => $m->quantity < 0])>{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                                <td class="text-end">{{ $m->balance_after }}</td>
                                <td class="small">
                                    {{ $m->department?->name }}
                                    @if ($m->reference instanceof \App\Models\Requisition)
                                        <a href="{{ route('requisitions.show', $m->reference) }}">{{ $m->reference->requisition_number }}</a>
                                    @elseif ($m->reference instanceof \App\Models\PurchaseOrder)
                                        {{ $m->reference->po_number }}
                                    @endif
                                </td>
                                <td class="small">{{ $m->user?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())<div class="card-footer bg-white">{{ $movements->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
