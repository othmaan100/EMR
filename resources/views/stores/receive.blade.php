@extends('layouts.app')

@section('title', 'Receive into store')

@section('content')
@php
    $lines = old('lines', [[]]);
@endphp
<div class="alert alert-light border small">
    Use this for stock that arrives <strong>without a purchase order</strong> (donations, petty-cash purchases, opening balances).
    Deliveries against an order are received on the purchase order.
</div>
<form method="POST" action="{{ route('stores.receive.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <x-form.input name="source" label="Source / reference" required maxlength="150" placeholder="e.g. Opening balance, donation from State Ministry of Health" />
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            Items <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body">
            @error('lines')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div id="lines">
                @foreach ($lines as $i => $line)
                    <div class="row g-2 mb-2" data-line>
                        <div class="col-md-6">
                            <select name="lines[{{ $i }}][store_item_id]" class="form-select form-select-sm @error("lines.$i.store_item_id") is-invalid @enderror" aria-label="Item">
                                <option value="">Select item…</option>
                                @foreach ($items as $it)<option value="{{ $it->id }}" @selected(($line['store_item_id'] ?? null) == $it->id)>{{ $it->label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" min="1" class="form-control form-control-sm @error("lines.$i.quantity") is-invalid @enderror" placeholder="Qty" aria-label="Quantity"></div>
                        <div class="col-md-3">
                            <div class="input-group input-group-sm"><span class="input-group-text">{{ setting('currency_symbol') }}</span>
                                <input type="number" name="lines[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" min="0" step="0.01" class="form-control" placeholder="Unit cost" aria-label="Unit cost"></div>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Leave unit cost blank for donations; the item keeps its current average cost.</div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Receive into store</button></div>
    </div>
</form>
@include('stores._lines-js')
@endsection
