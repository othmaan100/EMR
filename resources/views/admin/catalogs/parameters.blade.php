@extends('layouts.app')

@section('title', 'Result Fields — '.$test->name)

@section('content')
<a href="{{ route('admin.catalogs.index', 'lab-tests') }}" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left me-1"></i> Lab tests</a>

<div class="alert alert-info small">
    Each field becomes an entry box on the lab result form. <strong>Number</strong> fields are flagged Low/High against the reference range.
    <strong>Choice list</strong> and <strong>Free text</strong> fields are flagged "Abnormal" when they differ from the expected answer.
    Changes apply to new results only; past reports keep the ranges they were issued with.
    @if ($test->parameters->isEmpty())<br>With no fields, the lab enters a single free-text result.@endif
</div>

@php
    $row = function ($p = null) use ($test) {
        return [
            'action' => $p ? route('admin.lab-parameters.update', [$test, $p]) : route('admin.lab-parameters.store', $test),
            'p' => $p,
        ];
    };
@endphp

<div class="card mb-3">
    <div class="card-header">{{ $test->name }} <span class="text-muted fw-normal">({{ $test->code }})</span></div>
    <div class="card-body">
        <div class="row g-2 small fw-semibold text-muted d-none d-lg-flex mb-1">
            <div class="col-lg-1">Order</div><div class="col-lg-2">Name</div><div class="col-lg-1">Unit</div><div class="col-lg-2">Type</div>
            <div class="col-lg-1">Low</div><div class="col-lg-1">High</div><div class="col-lg-2">Expected / choices</div><div class="col-lg-2"></div>
        </div>
        @foreach ([...$test->parameters, null] as $p)
            @php $r = $row($p); @endphp
            <form method="POST" action="{{ $r['action'] }}" @class(['row g-2 align-items-center py-2', 'border-top' => ! $loop->first, 'bg-light rounded' => ! $p])>
                @csrf
                @if ($p) @method('PUT') @endif
                <div class="col-lg-1"><input type="number" name="sort_order" value="{{ $p?->sort_order ?? $test->parameters->count() }}" min="0" class="form-control form-control-sm" aria-label="Order"></div>
                <div class="col-lg-2"><input type="text" name="name" value="{{ $p?->name }}" required maxlength="100" class="form-control form-control-sm" placeholder="{{ $p ? '' : 'New field name' }}" aria-label="Name"></div>
                <div class="col-lg-1"><input type="text" name="unit" value="{{ $p?->unit }}" maxlength="30" class="form-control form-control-sm" placeholder="unit" aria-label="Unit"></div>
                <div class="col-lg-2">
                    <select name="type" class="form-select form-select-sm" aria-label="Type">
                        @foreach (\App\Models\LabTestParameter::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(($p?->type ?? 'numeric') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1"><input type="text" name="ref_low" value="{{ $p?->ref_low }}" inputmode="decimal" class="form-control form-control-sm" placeholder="low" aria-label="Low"></div>
                <div class="col-lg-1"><input type="text" name="ref_high" value="{{ $p?->ref_high }}" inputmode="decimal" class="form-control form-control-sm" placeholder="high" aria-label="High"></div>
                <div class="col-lg-2">
                    <input type="text" name="ref_text" value="{{ $p?->ref_text }}" maxlength="50" class="form-control form-control-sm mb-1" placeholder="expected (e.g. Negative)" aria-label="Expected">
                    <input type="text" name="options" value="{{ $p?->options ? implode(', ', $p->options) : '' }}" class="form-control form-control-sm" placeholder="choices, comma-separated" aria-label="Choices">
                </div>
                <div class="col-lg-2 d-flex gap-1">
                    <button class="btn btn-sm {{ $p ? 'btn-outline-primary' : 'btn-primary' }} flex-fill">{{ $p ? 'Save' : 'Add field' }}</button>
                    @if ($p)
                        <button type="submit" form="del-{{ $p->id }}" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>
                    @endif
                </div>
            </form>
            @if ($p)
                <form id="del-{{ $p->id }}" method="POST" action="{{ route('admin.lab-parameters.destroy', [$test, $p]) }}" class="d-none" onsubmit="return confirm('Delete this field?')">
                    @csrf @method('DELETE')
                </form>
            @endif
        @endforeach
        @if ($errors->any())<div class="alert alert-danger small mt-2 mb-0">{{ $errors->first() }}</div>@endif
    </div>
</div>
@endsection
