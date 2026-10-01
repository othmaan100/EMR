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
    $p = $admission->patient;
@endphp
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'DISCHARGE SUMMARY', 'docNumber' => $admission->admission_number,
        'docDate' => $admission->discharged_at ? format_date($admission->discharged_at, true) : 'Not yet discharged'])

    <table class="table table-sm table-borderless small mb-2">
        <tr><td class="text-muted" style="width: 18%">Patient</td><td class="fw-semibold">{{ $p->full_name }}</td>
            <td class="text-muted" style="width: 18%">Hospital no.</td><td class="fw-semibold">{{ $p->hospital_number }}</td></tr>
        <tr><td class="text-muted">Sex / Age</td><td>{{ ucfirst($p->gender) }} / {{ $p->age ?? '—' }}</td>
            <td class="text-muted">Ward</td><td>{{ $admission->ward->name }}</td></tr>
        <tr><td class="text-muted">Admitted</td><td>{{ format_date($admission->admitted_at, true) }}</td>
            <td class="text-muted">Length of stay</td><td>{{ $admission->lengthOfStay() }} day(s)</td></tr>
        <tr><td class="text-muted">Outcome</td><td>{{ \App\Models\Admission::DISCHARGE_TYPES[$admission->discharge_type] ?? '—' }}</td>
            <td class="text-muted">Consultant</td><td>{{ $admission->doctor?->name ?? '—' }}</td></tr>
        <tr><td class="text-muted">Allergies</td><td colspan="3" class="{{ $p->allergies ? 'fw-bold text-danger' : '' }}">{{ $p->allergies ?: 'None known' }}</td></tr>
    </table>

    <h6>Reason for admission</h6>
    <div style="white-space: pre-line;">{{ $admission->reason }}</div>

    <h6>Final diagnosis</h6>
    <div class="fw-semibold" style="white-space: pre-line;">{{ $admission->final_diagnosis }}</div>

    <h6>Summary of stay</h6>
    <div style="white-space: pre-line;">{{ $admission->discharge_summary }}</div>

    @php
        $labs = $admission->labOrders->where('status', 'completed');
        $imaging = $admission->imagingOrders->where('status', 'completed');
    @endphp
    @if ($labs->isNotEmpty() || $imaging->isNotEmpty())
        <h6>Key investigations</h6>
        <ul class="small mb-0">
            @foreach ($labs as $order)
                @foreach ($order->items as $item)
                    <li>{{ $item->test->name }}: {{ $item->results->map(fn ($r) => $r->name.' '.$r->value.($r->unit ? ' '.$r->unit : '').($r->flag ? ' ('.strtoupper(substr($r->flag, 0, 1)).')' : ''))->implode('; ') }}</li>
                @endforeach
            @endforeach
            @foreach ($imaging as $order)<li>{{ $order->test->name }}: {{ $order->impression }}</li>@endforeach
        </ul>
    @endif

    @if ($admission->discharge_medications)
        <h6>Medications on discharge</h6>
        <div style="white-space: pre-line;">{{ $admission->discharge_medications }}</div>
    @endif

    @if ($admission->follow_up)
        <h6>Follow-up & advice</h6>
        <div style="white-space: pre-line;">{{ $admission->follow_up }}</div>
    @endif

    <div class="d-flex justify-content-end mt-5">
        <div class="text-center" style="min-width: 240px;">
            <div style="border-top: 1px solid #333;" class="pt-1">{{ $admission->dischargedBy?->name }}</div>
            <div class="small text-muted">{{ $admission->dischargedBy?->designation ?? 'Discharging clinician' }}</div>
        </div>
    </div>
</div>
</body>
</html>
