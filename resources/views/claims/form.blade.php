<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .box { border: 1px solid #ced4da; border-radius: 4px; padding: 8px 12px; margin-bottom: 12px; }
        .box h6 { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin-bottom: 6px; }
        @media print { @page { size: A4; margin: 12mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
@php
    $p = $bill->patient;
    $dx = $bill->diagnoses();
    $claimable = $bill->items->whereNull('voided_at')->where('insurance_amount', '>', 0);
    $admission = $bill->admission;
    $doctor = $bill->visit?->consultation?->doctor ?? $admission?->doctor;
@endphp
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'HEALTH INSURANCE CLAIM FORM', 'docNumber' => $bill->bill_number, 'docDate' => format_date($bill->created_at)])

    <div class="row g-2 small">
        <div class="col-6">
            <div class="box h-100">
                <h6>Enrollee</h6>
                <div><span class="text-muted">Name:</span> <strong>{{ $p->full_name }}</strong></div>
                <div><span class="text-muted">Enrollee ID:</span> <strong>{{ $p->insurance_number ?? '—' }}</strong></div>
                <div><span class="text-muted">Sex / age:</span> {{ ucfirst((string) $p->gender) }} · {{ $p->age ?? '—' }} @if ($p->date_of_birth)(DOB {{ format_date($p->date_of_birth) }})@endif</div>
                <div><span class="text-muted">Hospital no.:</span> {{ $p->hospital_number }}</div>
                <div><span class="text-muted">Phone:</span> {{ $p->phone ?? '—' }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="box h-100">
                <h6>Payer &amp; encounter</h6>
                <div><span class="text-muted">HMO / payer:</span> <strong>{{ $bill->insuranceProvider?->name }}</strong></div>
                <div><span class="text-muted">PA code:</span> <strong>{{ $bill->authorization_code ?? '—' }}</strong></div>
                <div><span class="text-muted">Type:</span>
                    @if ($admission)
                        Inpatient · {{ $admission->ward?->name }} · {{ format_date($admission->admitted_at) }} – {{ $admission->discharged_at ? format_date($admission->discharged_at) : 'in progress' }}
                        ({{ $admission->lengthOfStay() }} {{ Str::plural('day', $admission->lengthOfStay()) }})
                    @else
                        Outpatient · {{ $bill->visit?->clinic?->name ?? '—' }} · {{ $bill->visit ? format_date($bill->visit->checked_in_at) : format_date($bill->created_at) }}
                    @endif
                </div>
                @if ($bill->claimBatch)<div><span class="text-muted">Batch:</span> {{ $bill->claimBatch->batch_number }}</div>@endif
            </div>
        </div>
    </div>

    <div class="box small">
        <h6>Diagnosis</h6>
        @forelse ($dx as $d)
            <div>{{ $d->is_primary ? 'Primary: ' : '' }}{{ $d->description }} @if ($d->icd10_code)<strong>({{ $d->icd10_code }})</strong>@endif <span class="text-muted">— {{ $d->certainty }}</span></div>
        @empty
            <div>{{ $admission?->final_diagnosis ?? '—' }}</div>
        @endforelse
    </div>

    <table class="table table-sm table-bordered small align-middle">
        <thead class="table-light"><tr><th>#</th><th>Date</th><th>Service / item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th><th class="text-end">Claimed</th></tr></thead>
        <tbody>
            @foreach ($claimable->values() as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="text-nowrap">{{ format_date($item->created_at) }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="text-end">{{ money($item->unit_price, false) }}</td>
                    <td class="text-end">{{ money($item->amount, false) }}</td>
                    <td class="text-end">{{ money($item->insurance_amount, false) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-semibold">
                <td colspan="5" class="text-end">Total</td>
                <td class="text-end">{{ money($claimable->sum('amount')) }}</td>
                <td class="text-end">{{ money($claimable->sum('insurance_amount')) }}</td>
            </tr>
        </tfoot>
    </table>
    @if ($claimable->sum('patient_amount') > 0)
        <div class="small text-muted mb-3">Co-payment by enrollee: {{ money($claimable->sum('patient_amount')) }}</div>
    @endif

    <div class="row small mt-5 g-4">
        <div class="col-4"><div class="border-top pt-1">Attending doctor{{ $doctor ? ': '.$doctor->name : '' }}</div></div>
        <div class="col-4"><div class="border-top pt-1">Enrollee signature / thumbprint</div></div>
        <div class="col-4"><div class="border-top pt-1">Hospital stamp &amp; date</div></div>
    </div>
</div>
</body>
</html>
