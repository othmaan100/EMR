@extends('layouts.app')

@section('title', 'Receive Stock')

@section('content')
@php
    $lines = old('lines', [[]]);
@endphp
<form method="POST" action="{{ route('inventory.receive.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-header">Delivery</div>
        <div class="card-body">
            <div class="row g-3">
                <x-form.select name="supplier_id" label="Supplier" :options="$suppliers->all()" placeholder="— Select —" col="col-md-4" />
                <x-form.input name="invoice_number" label="Supplier invoice / delivery note no." col="col-md-3" />
                <x-form.input name="received_on" type="date" label="Date received" :value="today()->toDateString()" required col="col-md-2" max="{{ today()->toDateString() }}" />
                <x-form.input name="notes" label="Notes" col="col-md-3" />
            </div>
            @can('inventory.manage')
                <div class="small mt-2"><a href="{{ route('suppliers.create') }}">+ Add a new supplier</a></div>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            Items
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body">
            @error('lines')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div class="row g-2 small fw-semibold text-muted d-none d-lg-flex mb-1">
                <div class="col-lg-4">Drug</div><div class="col-lg-2">Batch no.</div><div class="col-lg-2">Expiry</div><div class="col-lg-1">Qty</div><div class="col-lg-2">Unit cost</div>
            </div>
            <div id="lines">
                @foreach ($lines as $i => $line)
                    <div class="row g-2 mb-2 align-items-start" data-line>
                        <div class="col-lg-4">
                            <select name="lines[{{ $i }}][drug_id]" class="form-select form-select-sm @error("lines.$i.drug_id") is-invalid @enderror" aria-label="Drug">
                                <option value="">Select drug…</option>
                                @foreach ($drugs as $d)
                                    <option value="{{ $d->id }}" @selected(($line['drug_id'] ?? null) == $d->id)>{{ $d->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2"><input type="text" name="lines[{{ $i }}][batch_number]" value="{{ $line['batch_number'] ?? '' }}" maxlength="50"
                                                     class="form-control form-control-sm @error("lines.$i.batch_number") is-invalid @enderror" placeholder="Batch" aria-label="Batch"></div>
                        <div class="col-lg-2"><input type="date" name="lines[{{ $i }}][expiry_date]" value="{{ $line['expiry_date'] ?? '' }}"
                                                     class="form-control form-control-sm @error("lines.$i.expiry_date") is-invalid @enderror" aria-label="Expiry"></div>
                        <div class="col-lg-1"><input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" min="1"
                                                     class="form-control form-control-sm @error("lines.$i.quantity") is-invalid @enderror" placeholder="Qty" aria-label="Quantity"></div>
                        <div class="col-lg-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ setting('currency_symbol') }}</span>
                                <input type="number" name="lines[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" min="0" step="0.01" class="form-control" aria-label="Unit cost">
                            </div>
                        </div>
                        <div class="col-lg-1"><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
                        @foreach (['drug_id', 'batch_number', 'expiry_date', 'quantity'] as $f)
                            @error("lines.$i.$f")<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button class="btn btn-primary px-4"><i class="bi bi-box-arrow-in-down me-1"></i> Receive into stock</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">Recent deliveries</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead class="table-light"><tr><th class="ps-3">GRN</th><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Lines</th><th>Received by</th></tr></thead>
            <tbody>
                @forelse ($recent as $r)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $r->receipt_number }}</td>
                        <td>{{ format_date($r->received_on) }}</td>
                        <td>{{ $r->supplier?->name ?? '—' }}</td>
                        <td>{{ $r->invoice_number ?? '—' }}</td>
                        <td class="text-end">{{ $r->batches_count }}</td>
                        <td>{{ $r->receiver?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No deliveries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    // Clone the last row for a new line, renumbering the field indexes.
    document.getElementById('add-line').addEventListener('click', () => {
        const container = document.getElementById('lines');
        const rows = container.querySelectorAll('[data-line]');
        const clone = rows[rows.length - 1].cloneNode(true);
        const index = rows.length;
        clone.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/lines\[\d+\]/, `lines[${index}]`);
            el.value = '';
            el.classList.remove('is-invalid');
        });
        clone.querySelectorAll('.text-danger.small').forEach((el) => el.remove());
        container.appendChild(clone);
    });
    document.getElementById('lines').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-line]');
        if (btn && document.querySelectorAll('[data-line]').length > 1) btn.closest('[data-line]').remove();
    });
</script>
@endsection
