@extends('layouts.app')

@section('title', 'New requisition')

@section('content')
@php
    $lines = old('lines', [[]]);
@endphp
<form method="POST" action="{{ route('requisitions.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body row g-3">
            <x-form.select name="department_id" label="For department / ward" :options="$departments->all()" :value="$defaultDepartment" placeholder="Choose…" required col="col-md-5" />
            <x-form.input name="needed_by" type="date" label="Needed by" col="col-md-3" :min="today()->toDateString()" />
            <x-form.input name="notes" label="Notes" col="col-md-4" maxlength="1000" />
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            Items requested <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body">
            @error('lines')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div id="lines">
                @foreach ($lines as $i => $line)
                    <div class="row g-2 mb-2" data-line>
                        <div class="col-md-8">
                            <select name="lines[{{ $i }}][store_item_id]" class="form-select form-select-sm @error("lines.$i.store_item_id") is-invalid @enderror" aria-label="Item">
                                <option value="">Select item…</option>
                                @foreach ($items->groupBy('category') as $category => $group)
                                    <optgroup label="{{ $category }}">
                                        @foreach ($group as $it)
                                            <option value="{{ $it->id }}" @selected(($line['store_item_id'] ?? null) == $it->id)>{{ $it->label }}{{ $it->quantity_on_hand <= 0 ? ' — out of stock' : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" min="1" class="form-control form-control-sm @error("lines.$i.quantity") is-invalid @enderror" placeholder="Quantity" aria-label="Quantity"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Send to store</button></div>
    </div>
</form>
@include('stores._lines-js')
@endsection
