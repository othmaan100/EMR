<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 480px; margin: 0 auto; background: #fff; padding: 24px; position: relative; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 10px; margin-bottom: 12px; }
        .void { position: absolute; top: 35%; left: 0; right: 0; text-align: center; font-size: 60px; font-weight: 800; color: rgba(220,53,69,.2); transform: rotate(-20deg); }
        @media print { @page { margin: 8mm; } body { background: #fff; } .sheet { box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @if ($payment->isVoided())<div class="void">REVERSED</div>@endif
    @include('billing._letterhead', ['docTitle' => 'RECEIPT', 'docNumber' => $payment->receipt_number, 'docDate' => format_date($payment->created_at, true)])

    <div class="small mb-2">
        Received from <strong>{{ $payment->patient->full_name }}</strong> ({{ $payment->patient->hospital_number }})
    </div>

    <table class="table table-sm small">
        <thead class="table-light"><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            @php
                $unallocated = round($payment->amount - $payment->allocations->sum('amount'), 2);
            @endphp
            @if ($unallocated > 0)
                <tr><td>Deposit / advance payment (held as credit)</td><td class="text-end">{{ money($unallocated) }}</td></tr>
            @endif
            @foreach ($payment->allocations as $a)
                <tr><td>{{ $a->item->description }} <span class="text-muted">({{ $a->item->bill->bill_number }})</span></td><td class="text-end">{{ money($a->amount) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-bold"><td>Total paid</td><td class="text-end">{{ money($payment->amount) }}</td></tr>
        </tfoot>
    </table>

    <div class="small">
        <div>Method: {{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}{{ $payment->reference ? ' · Ref: '.$payment->reference : '' }}</div>
        <div>Cashier: {{ $payment->receiver?->name }}</div>
        <div class="mt-1">Balance still owed: <strong>{{ money($payment->patient->outstandingBalance()) }}</strong></div>
        @if ($payment->isVoided())<div class="text-danger mt-1">Reversed {{ format_date($payment->voided_at, true) }}: {{ $payment->void_reason }}</div>@endif
    </div>
    <div class="text-center small text-muted mt-3">Thank you. Please keep this receipt.</div>
</div>
</body>
</html>
