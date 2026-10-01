@extends('layouts.app')

@section('title', 'Supplier invoices')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Owed to suppliers</div>
            <div class="fs-4 fw-semibold">{{ money($owed) }}</div>
            @if ($overdue > 0)<div class="small text-danger">{{ money($overdue) }} overdue</div>@endif
        </div></div>
    </div>
    <div class="col-md-6">
        <details class="card h-100" @if ($errors->any()) open @endif>
            <summary class="card-header">Record a supplier invoice</summary>
            <form method="POST" action="{{ route('invoices.store') }}" class="card-body row g-2">
                @csrf
                <x-form.select name="supplier_id" label="Supplier" :options="$suppliers->all()" placeholder="Choose…" required col="col-md-6" />
                <div class="col-md-6">
                    <label for="purchase_order_id" class="form-label">Purchase order</label>
                    <select id="purchase_order_id" name="purchase_order_id" @class(['form-select', 'is-invalid' => $errors->has('purchase_order_id')])>
                        <option value="">— None —</option>
                        @foreach ($orders as $o)<option value="{{ $o->id }}" @selected(old('purchase_order_id') == $o->id)>{{ $o->po_number }}</option>@endforeach
                    </select>
                    @error('purchase_order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <x-form.input name="invoice_number" label="Invoice no." required col="col-md-4" maxlength="50" />
                <x-form.input name="invoice_date" type="date" label="Invoice date" required col="col-md-4" :max="today()->toDateString()" />
                <x-form.input name="due_date" type="date" label="Due date" col="col-md-4" />
                <x-form.input name="amount" type="number" label="Amount" required col="col-md-6" step="0.01" min="0.01" />
                <x-form.input name="notes" label="Notes" col="col-md-6" maxlength="500" />
                <div class="col-12 text-end"><button class="btn btn-primary">Save invoice</button></div>
            </form>
        </details>
    </div>
</div>

@error('status')<div class="alert alert-danger">{{ $message }}</div>@enderror

<ul class="nav nav-pills mb-3">
    @foreach (['unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid'] as $key => $label)
        <li class="nav-item"><a href="{{ route('invoices.index', ['tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key])>{{ $label }}</a></li>
    @endforeach
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Supplier</th><th>Invoice</th><th>PO</th><th>Due</th><th class="text-end">Amount</th><th style="min-width: 18rem;">{{ $tab === 'paid' ? 'Paid' : 'Pay' }}</th></tr></thead>
            <tbody>
                @forelse ($invoices as $inv)
                    <tr>
                        <td class="ps-3">{{ $inv->supplier->name }}</td>
                        <td class="small">{{ $inv->invoice_number }}<span class="d-block text-muted">{{ format_date($inv->invoice_date) }} · entered by {{ $inv->recorder?->name }}</span></td>
                        <td class="small">
                            @if ($inv->purchaseOrder)<a href="{{ route('purchasing.show', $inv->purchaseOrder) }}">{{ $inv->purchaseOrder->po_number }}</a>@else — @endif
                            @if ($mismatch[$inv->id])<span class="d-block text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $mismatch[$inv->id] }}</span>@endif
                        </td>
                        <td class="small">
                            {{ $inv->due_date ? format_date($inv->due_date) : '—' }}
                            @if ($inv->isOverdue())<span class="badge text-bg-danger">Overdue</span>@endif
                        </td>
                        <td class="text-end fw-semibold">{{ money($inv->amount) }}</td>
                        <td>
                            @if ($inv->status === 'paid')
                                <span class="small">{{ format_date($inv->paid_on) }} · ref {{ $inv->payment_reference }}</span>
                            @else
                                <form method="POST" action="{{ route('invoices.pay', $inv) }}" class="d-flex gap-1">
                                    @csrf
                                    <input type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="form-control form-control-sm" aria-label="Paid on">
                                    <input type="text" name="payment_reference" required maxlength="100" placeholder="Transfer / cheque ref" class="form-control form-control-sm" aria-label="Payment reference">
                                    <button class="btn btn-sm btn-success">Paid</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())<div class="card-footer bg-white">{{ $invoices->links() }}</div>@endif
</div>
<p class="small text-muted mt-2">For control, an invoice must be paid by someone other than the person who entered it.</p>
@endsection
