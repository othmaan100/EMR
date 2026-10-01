@extends('layouts.app')

@section('title', 'Dispense '.$prescription->prescription_number)

@section('content')
@php
    $open = in_array($prescription->status, ['pending', 'partially_dispensed'], true);
    $anyConflict = $conflicts->filter()->isNotEmpty();
    $reasons = ['Out of stock – patient to buy outside', 'Not stocked by this pharmacy', 'Patient declined', 'Prescriber cancelled'];
@endphp

@include('patients._mini-banner')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <strong>{{ $prescription->prescription_number }}</strong>
        <span class="badge text-bg-{{ $prescription->statusColor() }}">{{ $prescription->statusLabel() }}</span>
        <span class="small text-muted ms-2">Prescribed by {{ $prescription->prescriber?->name }}, {{ format_date($prescription->created_at, true) }}</span>
        @if ($prescription->dispensed_at)
            <span class="small text-muted ms-2">· Last dispensed {{ format_date($prescription->dispensed_at, true) }} by {{ $prescription->dispenser?->name }}</span>
        @endif
    </div>
    <a href="{{ route('prescriptions.print', $prescription) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print prescription</a>
</div>

@error('allergy')<div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i> {{ $message }}</div>@enderror

<form method="POST" action="{{ route('pharmacy.dispense', $prescription) }}">
    @csrf
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Medication</th><th>Directions</th><th class="text-center">Prescribed</th><th class="text-center">Given</th><th class="text-center">In stock</th>
                        @if ($open)<th style="width: 14rem;">Dispense now</th>@endif</tr>
                </thead>
                <tbody>
                    @foreach ($prescription->items as $item)
                        @php
                            $inStock = $stock[$item->id];
                            $default = $item->remaining() ?? ($item->quantity_dispensed ? null : '');
                        @endphp
                        <tr @class(['table-light' => $item->isComplete()])>
                            <td class="ps-3">
                                <span class="fw-semibold">{{ $item->drug_name }}</span>
                                @if ($conflicts[$item->id])
                                    <span class="badge text-bg-danger d-inline-block mt-1"><i class="bi bi-exclamation-triangle"></i> Allergy: {{ implode(', ', $conflicts[$item->id]) }}</span>
                                @endif
                                @if ($item->allergy_override)<span class="badge text-bg-warning">Prescriber overrode allergy</span>@endif
                                @if ($item->instructions)<div class="small text-muted">{{ $item->instructions }}</div>@endif
                                @if ($item->not_dispensed_reason)<div class="small text-danger"><i class="bi bi-x-circle"></i> {{ $item->not_dispensed_reason }}</div>@endif
                            </td>
                            <td class="small">{{ $item->directions() }}</td>
                            <td class="text-center">{{ $item->quantity ?? '—' }}</td>
                            <td class="text-center">{{ $item->quantity_dispensed }}</td>
                            <td class="text-center">
                                @if ($item->drug_id)
                                    <span @class(['fw-semibold', 'text-danger' => $inStock === 0])>{{ $inStock }}</span>
                                    <span class="small text-muted d-block">{{ $item->drug->unit }}</span>
                                @else
                                    <span class="badge text-bg-light border" title="Not a formulary item">free text</span>
                                @endif
                            </td>
                            @if ($open)
                                <td>
                                    @if ($item->isComplete())
                                        <span class="text-success small"><i class="bi bi-check-circle"></i> Done</span>
                                    @else
                                        @php
                                            $priced = $item->pricedQuantity();
                                            $locked = $payFirst && $priced > 0 && $due[$item->id] > 0;
                                        @endphp
                                        @if ($payFirst && $priced > 0)
                                            @if ($locked)
                                                <div class="small text-warning-emphasis mb-1"><i class="bi bi-hourglass-split"></i> {{ $priced }} priced — awaiting payment of {{ money($due[$item->id]) }}</div>
                                            @else
                                                <div class="small text-success mb-1"><i class="bi bi-cash-coin"></i> {{ $priced }} paid — ready to dispense</div>
                                            @endif
                                        @endif
                                        @if ($item->drug_id && ! $locked)
                                            <input type="number" name="lines[{{ $item->id }}][quantity]" min="0" max="{{ ($payFirst && $priced) ? $priced : ($item->remaining() ?? 100000) }}"
                                                   value="{{ old("lines.{$item->id}.quantity", ($payFirst && $priced) ? $priced : ($inStock > 0 ? $default : '')) }}"
                                                   @class(['form-control form-control-sm mb-1', 'is-invalid' => $errors->has("lines.{$item->id}.quantity")])
                                                   placeholder="Qty" aria-label="Quantity to dispense">
                                            @error("lines.{$item->id}.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @endif
                                        <select name="lines[{{ $item->id }}][unavailable]" class="form-select form-select-sm" aria-label="Not available reason">
                                            <option value="">{{ $item->drug_id ? '— or mark not available —' : 'Mark as not available…' }}</option>
                                            @foreach ($reasons as $r)<option @selected(old("lines.{$item->id}.unavailable") === $r)>{{ $r }}</option>@endforeach
                                        </select>
                                        @error("lines.{$item->id}.unavailable")<div class="text-danger small">{{ $message }}</div>@enderror
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($open)
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    @if ($anyConflict)
                        <div class="form-check">
                            <input type="hidden" name="allergy_confirmed" value="0">
                            <input class="form-check-input" type="checkbox" name="allergy_confirmed" value="1" id="allergy_confirmed">
                            <label class="form-check-label fw-semibold text-danger" for="allergy_confirmed">
                                I have checked the allergy warning with the prescriber/patient
                            </label>
                        </div>
                    @elseif ($payFirst)
                        <span class="small text-muted"><i class="bi bi-info-circle"></i> Pay-first: price the quantities, the patient pays at the cashier, then dispense.</span>
                    @else
                        <span class="small text-muted">Stock is taken from the batch closest to expiry first.</span>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    @if ($payFirst)
                        <button formaction="{{ route('pharmacy.price', $prescription) }}" class="btn btn-outline-primary">
                            <i class="bi bi-receipt me-1"></i> Price &amp; send to cashier
                        </button>
                    @endif
                    <button class="btn btn-primary px-4"><i class="bi bi-bag-check me-1"></i> {{ $payFirst ? 'Dispense paid items' : 'Dispense' }}</button>
                </div>
            </div>
        @endif
    </div>
</form>
@endsection
