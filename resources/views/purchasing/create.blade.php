@extends('layouts.app')

@section('title', 'New purchase order')

@section('content')
@php
    $lines = old('lines', $prefill ?: [[]]);
@endphp
<form method="POST" action="{{ route('purchasing.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <x-form.select name="supplier_id" label="Supplier" :options="$suppliers->all()" placeholder="Choose…" required />
                <div class="small" style="margin-top: -.5rem;"><a href="{{ route('suppliers.create') }}">+ Add a supplier</a></div>
            </div>
            <x-form.input name="order_date" type="date" label="Order date" :value="today()->toDateString()" required col="col-md-3" :max="today()->toDateString()" />
            <x-form.input name="expected_date" type="date" label="Expected delivery" col="col-md-4" />
            <x-form.input name="notes" label="Notes / delivery instructions" col="col-12" maxlength="1000" />
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            Items <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body">
            @error('lines')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            @if ($prefill)<div class="alert alert-info py-2 small">Filled with items at or below their reorder level. Adjust quantities and prices.</div>@endif
            <div class="row g-2 small fw-semibold text-muted d-none d-md-flex mb-1"><div class="col-md-6">Item</div><div class="col-md-2">Quantity</div><div class="col-md-3">Unit price</div></div>
            <div id="lines">
                @foreach ($lines as $i => $line)
                    <div class="row g-2 mb-2" data-line>
                        <div class="col-md-6">
                            <select name="lines[{{ $i }}][item]" class="form-select form-select-sm @error("lines.$i.item") is-invalid @enderror" aria-label="Item">
                                <option value="">Select item…</option>
                                @foreach ($options as $group => $list)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($list as $value => $label)<option value="{{ $value }}" @selected(($line['item'] ?? null) === $value)>{{ $label }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" min="1" class="form-control form-control-sm @error("lines.$i.quantity") is-invalid @enderror" placeholder="Qty" aria-label="Quantity"></div>
                        <div class="col-md-3">
                            <div class="input-group input-group-sm"><span class="input-group-text">{{ setting('currency_symbol') }}</span>
                                <input type="number" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" min="0" step="0.01" class="form-control @error("lines.$i.unit_price") is-invalid @enderror" aria-label="Unit price"></div>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Drugs are received into pharmacy stock (with batch and expiry); store items into the general store.</div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Save draft</button></div>
    </div>
</form>
@include('stores._lines-js')
@endsection
