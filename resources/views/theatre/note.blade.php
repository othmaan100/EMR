<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        h6 { text-transform: uppercase; color: #6c757d; font-size: .75rem; margin: 1rem 0 .25rem; }
        @media print { @page { size: A4; margin: 12mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
@php
    $p = $surgery->patient;
@endphp
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'OPERATION NOTE', 'docNumber' => $surgery->surgery_number, 'docDate' => format_date($surgery->completed_at ?? $surgery->scheduled_at, true)])

    <table class="table table-sm table-borderless small mb-2">
        <tr><td class="text-muted" style="width: 18%">Patient</td><td class="fw-semibold">{{ $p->full_name }}</td>
            <td class="text-muted" style="width: 18%">Hospital no.</td><td class="fw-semibold">{{ $p->hospital_number }}</td></tr>
        <tr><td class="text-muted">Sex / Age</td><td>{{ ucfirst($p->gender) }} / {{ $p->age ?? '—' }}</td>
            <td class="text-muted">Theatre</td><td>{{ $surgery->theatre->name }}</td></tr>
        <tr><td class="text-muted">Surgeon</td><td>{{ $surgery->surgeon?->name ?? '—' }}</td>
            <td class="text-muted">Assistant</td><td>{{ $surgery->assistant ?: '—' }}</td></tr>
        <tr><td class="text-muted">Anaesthetist</td><td>{{ $surgery->anaesthetist?->name ?? '—' }}</td>
            <td class="text-muted">Anaesthesia</td><td>{{ \App\Models\Surgery::ANAESTHESIA[$surgery->anaesthesia_type] ?? '—' }}</td></tr>
        <tr><td class="text-muted">Times</td><td colspan="3">In {{ $surgery->in_theatre_at?->format('H:i') ?? '—' }} · Incision {{ $surgery->incision_at?->format('H:i') ?? '—' }} · Out {{ $surgery->out_at?->format('H:i') ?? '—' }} · {{ \App\Models\Surgery::URGENCY[$surgery->urgency]['label'] }}</td></tr>
    </table>

    <h6>Indication</h6><div style="white-space: pre-line;">{{ $surgery->indication }}</div>
    <h6>Procedure</h6><div class="fw-semibold" style="white-space: pre-line;">{{ $surgery->procedure_performed ?: $surgery->procedure_name }}</div>
    <h6>Findings</h6><div style="white-space: pre-line;">{{ $surgery->findings }}</div>
    <table class="table table-sm table-borderless small mt-2 mb-0">
        <tr><td class="text-muted" style="width: 18%">Blood loss</td><td>{{ $surgery->blood_loss_ml !== null ? $surgery->blood_loss_ml.' ml' : '—' }}</td>
            <td class="text-muted" style="width: 18%">Specimens</td><td>{{ $surgery->specimens ?: 'None' }}</td></tr>
        <tr><td class="text-muted">Drains</td><td>{{ $surgery->drains ?: 'None' }}</td><td class="text-muted">Implants</td><td>{{ $surgery->implants ?: 'None' }}</td></tr>
        <tr><td class="text-muted">Closure</td><td colspan="3">{{ $surgery->closure ?: '—' }}</td></tr>
    </table>
    <h6>Complications</h6><div>{{ $surgery->complications ?: 'None' }}</div>
    <h6>Post-operative orders</h6><div style="white-space: pre-line;">{{ $surgery->postop_orders }}</div>
    <h6>WHO Surgical Safety Checklist</h6>
    <div class="small">
        @foreach (\App\Models\Surgery::CHECKLIST as $phase => $def)
            {{ $def['title'] }}: {{ $surgery->phaseDone($phase) ? '✔ '.$surgery->checklist[$phase]['by_name'].' '.\Illuminate\Support\Carbon::parse($surgery->checklist[$phase]['at'])->format('H:i') : '—' }}@if (! $loop->last) · @endif
        @endforeach
    </div>

    <div class="d-flex justify-content-end mt-5">
        <div class="text-center" style="min-width: 240px;">
            <div style="border-top: 1px solid #333;" class="pt-1">{{ $surgery->surgeon?->name }}</div>
            <div class="small text-muted">Surgeon</div>
        </div>
    </div>
</div>
</body>
</html>
