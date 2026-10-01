<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        @media print { @page { size: A4; margin: 12mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
@php
    $t = $bill->totals();
    $p = $bill->patient;
@endphp
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'INVOICE', 'docNumber' => $bill->bill_number, 'docDate' => format_date($bill->created_at)])

    <table class="table table-sm table-borderless small mb-3">
        <tr><td class="text-muted" style="width: 18%">Patient</td><td class="fw-semibold">{{ $p->full_name }}</td>
            <td class="text-muted" style="width: 18%">Hospital no.</td><td class="fw-semibold">{{ $p->hospital_number }}</td></tr>
        <tr><td class="text-muted">Visit</td><td>{{ $bill->visit ? $bill->visit->visit_number.' · '.$bill->visit->clinic->name : '—' }}</td>
            <td class="text-muted">Payer</td><td>{{ $bill->insuranceProvider ? $bill->insuranceProvider->name.' · '.$p->insurance_number : 'Self-pay' }}</td></tr>
    </table>

    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Amount</th><th class="text-end">Insurance</th><th class="text-end">Patient</th></tr></thead>
        <tbody>
            @foreach ($bill->items as $item)
                <tr @class(['text-decoration-line-through text-muted' => $item->voided_at])>
                    <td>{{ $item->description }} <span class="small text-muted">{{ format_date($item->created_at) }}</span>
                        @if ($item->voided_at)<span class="small">(void: {{ $item->void_reason }})</span>@endif
                        @if ($item->discount_amount > 0 && ! $item->voided_at)<div class="small text-muted">Less discount {{ money($item->discount_amount) }} — {{ $item->discount_reason }}</div>@endif
                    </td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="text-end">{{ money($item->unit_price, false) }}</td>
                    <td class="text-end">{{ money($item->amount, false) }}</td>
                    <td class="text-end">{{ money($item->insurance_amount, false) }}</td>
                    <td class="text-end">{{ money($item->patient_amount, false) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row justify-content-end">
        <div class="col-md-6">
            <table class="table table-sm small">
                <tr><td>Total charges</td><td class="text-end">{{ money($t['amount']) }}</td></tr>
                <tr><td>Payable by {{ $bill->insuranceProvider?->name ?? 'insurance' }}</td><td class="text-end">{{ money($t['insurance']) }}</td></tr>
                <tr><td>Patient share</td><td class="text-end">{{ money($t['patient']) }}</td></tr>
                <tr><td>Discounts / waivers</td><td class="text-end">− {{ money($t['discount']) }}</td></tr>
                <tr><td>Paid</td><td class="text-end">− {{ money($t['paid']) }}</td></tr>
                <tr class="fw-bold fs-6"><td>Balance due from patient</td><td class="text-end">{{ money($t['balance']) }}</td></tr>
            </table>
        </div>
    </div>
    <div class="small text-muted">Printed {{ format_date(now(), true) }}</div>
</div>
</body>
</html>
