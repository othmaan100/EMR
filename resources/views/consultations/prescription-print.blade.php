<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .rx-symbol { font-size: 28px; font-weight: 700; color: var(--brand); font-family: serif; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; }
            .sheet { padding: 0; max-width: none; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    <div class="letterhead d-flex gap-3 align-items-center">
        @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="height:64px;width:64px;object-fit:contain">@endif
        <div class="flex-grow-1">
            <div class="h4 mb-0">{{ setting('hospital_name') }}</div>
            <div class="small text-muted">{{ collect([setting('address'), setting('city'), setting('state')])->filter()->implode(', ') }}</div>
            <div class="small text-muted">{{ collect([setting('phone'), setting('email')])->filter()->implode(' · ') }}</div>
        </div>
        <div class="text-end">
            <div class="fw-semibold">PRESCRIPTION</div>
            <div class="small">{{ $prescription->prescription_number }}</div>
            <div class="small">{{ format_date($prescription->created_at, true) }}</div>
        </div>
    </div>

    @php($p = $prescription->patient)
    <table class="table table-sm table-borderless small mb-3">
        <tr>
            <td class="text-muted" style="width: 18%">Patient</td><td class="fw-semibold">{{ $p->full_name }}</td>
            <td class="text-muted" style="width: 18%">Hospital no.</td><td class="fw-semibold">{{ $p->hospital_number }}</td>
        </tr>
        <tr>
            <td class="text-muted">Sex / Age</td><td>{{ ucfirst($p->gender) }} / {{ $p->age ?? '—' }}</td>
            <td class="text-muted">Clinic</td><td>{{ $prescription->visit?->clinic?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="text-muted">Allergies</td><td colspan="3" class="{{ $p->allergies ? 'fw-bold text-danger' : '' }}">{{ $p->allergies ?: 'None known' }}</td>
        </tr>
        @if ($diagnoses->isNotEmpty())
            <tr><td class="text-muted">Diagnosis</td><td colspan="3">{{ $diagnoses->map(fn ($d) => $d->description.($d->icd10_code ? " ({$d->icd10_code})" : ''))->implode('; ') }}</td></tr>
        @endif
    </table>

    <div class="rx-symbol mb-2">℞</div>
    <table class="table table-bordered align-middle">
        <thead class="table-light"><tr><th style="width: 5%">#</th><th>Medication</th><th>Directions</th><th style="width: 10%">Qty</th></tr></thead>
        <tbody>
            @foreach ($prescription->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="fw-semibold">{{ $item->drug_name }}</td>
                    <td>
                        {{ $item->dose }} {{ $item->route }} — {{ \App\Models\Prescription::FREQUENCIES[$item->frequency] ?? $item->frequency }} for {{ $item->duration }}
                        @if ($item->instructions)<div class="small text-muted">{{ $item->instructions }}</div>@endif
                    </td>
                    <td>{{ $item->quantity ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-end mt-5">
        <div class="small text-muted">Printed {{ format_date(now(), true) }}</div>
        <div class="text-center" style="min-width: 240px;">
            <div style="border-top: 1px solid #333;" class="pt-1">{{ $prescription->prescriber?->name }}</div>
            <div class="small text-muted">{{ $prescription->prescriber?->designation ?? 'Prescriber' }}</div>
        </div>
    </div>
</div>
</body>
</html>
