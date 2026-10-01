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
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'PURCHASE ORDER', 'docNumber' => $po->po_number, 'docDate' => format_date($po->order_date)])

    <div class="row small mb-3">
        <div class="col-6">
            <div class="text-muted">To</div>
            <div class="fw-semibold">{{ $po->supplier->name }}</div>
            <div>{{ $po->supplier->address }}</div>
            <div>{{ collect([$po->supplier->phone, $po->supplier->email])->filter()->implode(' · ') }}</div>
        </div>
        <div class="col-6 text-end">
            @if ($po->expected_date)<div><span class="text-muted">Deliver by:</span> {{ format_date($po->expected_date) }}</div>@endif
            <div><span class="text-muted">Deliver to:</span> {{ setting('hospital_name') }} stores</div>
        </div>
    </div>

    <table class="table table-sm table-bordered small">
        <thead class="table-light"><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            @foreach ($po->items as $i => $line)
                <tr><td>{{ $i + 1 }}</td><td>{{ $line->description }}</td><td class="text-end">{{ $line->quantity }}</td>
                    <td class="text-end">{{ money($line->unit_price, false) }}</td><td class="text-end">{{ money($line->lineTotal(), false) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end">{{ money($po->total) }}</td></tr></tfoot>
    </table>
    @if ($po->notes)<p class="small"><span class="text-muted">Notes:</span> {{ $po->notes }}</p>@endif
    <p class="small text-muted">Please quote {{ $po->po_number }} on your delivery note and invoice. Goods not matching this order may be refused.</p>

    <div class="row small mt-5">
        <div class="col-6"><div class="border-top pt-1">Prepared by: {{ $po->creator?->name }}</div></div>
        <div class="col-6"><div class="border-top pt-1">Approved by: {{ $po->approver?->name }} ({{ $po->approved_at ? format_date($po->approved_at) : '' }})</div></div>
    </div>
</div>
</body>
</html>
