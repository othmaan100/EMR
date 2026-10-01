<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 1100px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        @media print { @page { size: A4 landscape; margin: 10mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'CLAIM SCHEDULE', 'docNumber' => $batch->batch_number, 'docDate' => format_date($batch->submitted_at ?? now())])

    <table class="table table-sm table-borderless small mb-3">
        <tr><td class="text-muted" style="width: 15%">To</td><td class="fw-semibold">{{ $batch->insuranceProvider->name }} @if ($batch->insuranceProvider->code)({{ $batch->insuranceProvider->code }})@endif</td>
            <td class="text-muted" style="width: 15%">Period</td><td>{{ format_date($batch->period_from) }} – {{ format_date($batch->period_to) }}</td></tr>
        <tr><td class="text-muted">Provider</td><td>{{ setting('hospital_name') }} @if (setting('registration_number'))· Reg. {{ setting('registration_number') }}@endif</td>
            <td class="text-muted">Encounters</td><td>{{ $batch->bills->count() }}</td></tr>
    </table>

    <table class="table table-sm table-bordered small align-middle">
        <thead class="table-light">
            <tr><th>#</th><th>Date</th><th>Enrollee ID</th><th>Enrollee name</th><th>PA code</th><th>Diagnosis (ICD-10)</th><th>Type</th><th>Bill no.</th><th class="text-end">Amount claimed</th></tr>
        </thead>
        <tbody>
            @foreach ($batch->bills as $i => $bill)
                @php $dx = $bill->diagnoses(); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="text-nowrap">{{ format_date($bill->created_at) }}</td>
                    <td>{{ $bill->patient->insurance_number }}</td>
                    <td>{{ $bill->patient->full_name }}</td>
                    <td>{{ $bill->authorization_code }}</td>
                    <td>{{ $dx->map(fn ($d) => $d->description.($d->icd10_code ? " ({$d->icd10_code})" : ''))->implode('; ') ?: $bill->admission?->final_diagnosis }}</td>
                    <td>{{ $bill->admission_id ? 'IP' : 'OP' }}</td>
                    <td>{{ $bill->bill_number }}</td>
                    <td class="text-end">{{ money($bill->claim_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-semibold"><td colspan="8" class="text-end">Total</td><td class="text-end">{{ money($batch->amount_claimed) }}</td></tr>
        </tfoot>
    </table>

    <div class="row small mt-5">
        <div class="col-4"><div class="border-top pt-1">Prepared by</div></div>
        <div class="col-4"><div class="border-top pt-1">Medical Director / CMD</div></div>
        <div class="col-4"><div class="border-top pt-1">Received by (HMO) &amp; date</div></div>
    </div>
</div>
</body>
</html>
