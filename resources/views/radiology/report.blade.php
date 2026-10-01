<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 32px; position: relative; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 56px; font-weight: 800;
                     color: rgba(220, 53, 69, .15); transform: rotate(-20deg); pointer-events: none; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; }
            .sheet { padding: 0; max-width: none; box-shadow: none !important; }
            .toolbar, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php($p = $order->patient)
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @unless ($order->isReleased())
        <div class="watermark">DRAFT – NOT SIGNED</div>
    @endunless

    <div class="letterhead d-flex gap-3 align-items-center">
        @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="height:64px;width:64px;object-fit:contain">@endif
        <div class="flex-grow-1">
            <div class="h4 mb-0">{{ setting('hospital_name') }}</div>
            <div class="small text-muted">{{ collect([setting('address'), setting('city'), setting('state')])->filter()->implode(', ') }}</div>
            <div class="small text-muted">{{ collect([setting('phone'), setting('email')])->filter()->implode(' · ') }}</div>
        </div>
        <div class="text-end">
            <div class="fw-semibold">RADIOLOGY REPORT</div>
            <div class="small">{{ $order->order_number }}</div>
        </div>
    </div>

    <table class="table table-sm table-borderless small mb-3">
        <tr>
            <td class="text-muted" style="width: 17%">Patient</td><td class="fw-semibold">{{ $p->full_name }}</td>
            <td class="text-muted" style="width: 17%">Hospital no.</td><td class="fw-semibold">{{ $p->hospital_number }}</td>
        </tr>
        <tr>
            <td class="text-muted">Sex / Age</td><td>{{ ucfirst($p->gender) }} / {{ $p->age ?? '—' }}</td>
            <td class="text-muted">Referred by</td><td>{{ $order->orderedBy?->name }} ({{ $order->visit?->clinic?->name ?? '—' }})</td>
        </tr>
        <tr>
            <td class="text-muted">Examination</td><td class="fw-semibold">{{ $order->test->name }}</td>
            <td class="text-muted">Performed</td><td>{{ $order->performed_at ? format_date($order->performed_at, true) : '—' }}</td>
        </tr>
        <tr>
            <td class="text-muted">Clinical info</td>
            <td colspan="3">{{ collect([$order->clinical_notes, $diagnoses->pluck('description')->implode('; ')])->filter()->implode(' — ') }}</td>
        </tr>
    </table>

    @if ($order->findings || $order->impression)
        @include('radiology._report-body')
    @else
        <p class="text-muted">Not yet reported.</p>
    @endif

    @if ($order->attachments->isNotEmpty() && empty($portal))
        <div class="mt-4 no-print">
            <div class="fw-semibold small text-muted text-uppercase mb-2">Images</div>
            @include('radiology._attachments', ['removable' => false])
        </div>
    @endif

    <div class="d-flex justify-content-between mt-5 small">
        <div>
            <div class="text-muted">Radiographer</div>
            <div>{{ $order->performer?->name ?? '—' }}</div>
        </div>
        <div class="text-center" style="min-width: 240px;">
            <div style="border-top: 1px solid #333;" class="pt-1">{{ $order->reporter?->name ?? '—' }}</div>
            <div class="text-muted">Reporting radiologist{{ $order->completed_at ? ' · '.format_date($order->completed_at, true) : '' }}</div>
        </div>
    </div>
    <div class="small text-muted mt-3">Printed {{ format_date(now(), true) }}</div>
</div>
</body>
</html>
