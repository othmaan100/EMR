@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap gap-1">
        <a href="{{ route('purchasing.index') }}" @class(['badge text-decoration-none fs-6 fw-normal', 'text-bg-dark' => ! $status, 'text-bg-light border' => $status])>All</a>
        @foreach (\App\Models\PurchaseOrder::STATUSES as $key => $s)
            <a href="{{ route('purchasing.index', ['status' => $key]) }}" @class(['badge text-decoration-none fs-6 fw-normal', 'text-bg-'.$s['color'] => $status === $key, 'text-bg-light border' => $status !== $key])>
                {{ Str::before($s['label'], ' —') }} {{ $counts[$key] ?? 0 }}
            </a>
        @endforeach
    </div>
    <div class="d-flex gap-2">
        @can('payables.manage')
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i> Supplier invoices</a>
        @endcan
        @can('purchasing.manage')
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-truck me-1"></i> Suppliers</a>
            <a href="{{ route('purchasing.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New order</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">PO</th><th>Supplier</th><th>Ordered</th><th>Expected</th><th class="text-center">Lines</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($orders as $po)
                    <tr>
                        <td class="ps-3"><a href="{{ route('purchasing.show', $po) }}" class="fw-semibold text-decoration-none">{{ $po->po_number }}</a></td>
                        <td>{{ $po->supplier->name }}</td>
                        <td class="small">{{ format_date($po->order_date) }}</td>
                        <td class="small">
                            {{ $po->expected_date ? format_date($po->expected_date) : '—' }}
                            @if ($po->canReceive() && $po->expected_date?->lt(today()))<span class="badge text-bg-danger">Late</span>@endif
                        </td>
                        <td class="text-center">{{ $po->items_count }}</td>
                        <td class="text-end">{{ money($po->total) }}</td>
                        <td><span class="badge text-bg-{{ $po->statusColor() }}">{{ $po->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">No purchase orders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
</div>
@endsection
