<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        /* 50 × 25 mm specimen label */
        .label { width: 50mm; height: 25mm; background: #fff; padding: 1.5mm 2mm; font-size: 6.5pt; line-height: 1.2; overflow: hidden; }
        .label svg { width: 100%; height: 9mm; }
        .label .name { font-weight: 700; font-size: 7.5pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media print {
            @page { size: 50mm 25mm; margin: 0; }
            body { background: #fff; }
            .toolbar { display: none !important; }
            .wrap { padding: 0 !important; }
        }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print label</button>
</div>
<div class="wrap d-flex justify-content-center pb-4">
    <div class="label shadow-sm">
        <div class="name">{{ $order->patient->list_name }}</div>
        <div>{{ $order->patient->hospital_number }} · {{ strtoupper(substr($order->patient->gender, 0, 1)) }} · {{ $order->patient->age ?? '?' }}</div>
        <svg data-barcode="{{ $order->order_number }}" data-height="26" data-show-value="false"></svg>
        <div class="d-flex justify-content-between">
            <strong>{{ $order->order_number }}</strong>
            <span>{{ $order->collected_at?->format('d/m H:i') }}</span>
        </div>
        <div class="text-truncate">{{ $order->items->where('status', '!=', 'cancelled')->map(fn ($i) => $i->test->code)->implode(', ') }}</div>
    </div>
</div>
</body>
</html>
