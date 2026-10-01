<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        @media print { @page { size: A5 landscape; margin: 10mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
@php
    $p = $exam->patient;
    $fmt = fn ($v, $signed = true) => $v === null ? '—' : ($signed ? sprintf('%+.2f', $v) : $v);
@endphp
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'SPECTACLE PRESCRIPTION', 'docNumber' => $p->hospital_number, 'docDate' => format_date($exam->created_at)])

    <p class="mb-3"><strong>{{ $p->full_name }}</strong> · {{ ucfirst((string) $p->gender) }} · {{ $p->age ?? '' }}</p>

    <table class="table table-bordered text-center align-middle">
        <thead class="table-light"><tr><th></th><th>Sphere</th><th>Cylinder</th><th>Axis</th><th>Near add</th><th>VA (corrected)</th></tr></thead>
        <tbody>
            @foreach (['right' => 'Right (OD)', 'left' => 'Left (OS)'] as $eye => $label)
                <tr>
                    <th>{{ $label }}</th>
                    <td>{{ $exam->{"sph_{$eye}"} === null || $exam->{"sph_{$eye}"} == 0 ? 'Plano' : $fmt($exam->{"sph_{$eye}"}) }}</td>
                    <td>{{ $fmt($exam->{"cyl_{$eye}"}) }}</td>
                    <td>{{ $exam->{"axis_{$eye}"} !== null ? $exam->{"axis_{$eye}"}.'°' : '—' }}</td>
                    <td>{{ $fmt($exam->{"add_{$eye}"}) }}</td>
                    <td>{{ $exam->{"va_{$eye}_corrected"} ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small">
        @if ($exam->pd)<div>PD: {{ $exam->pd }} mm</div>@endif
        @if ($exam->lens_notes)<div>Lens: {{ $exam->lens_notes }}</div>@endif
    </div>
    <div class="row small mt-5">
        <div class="col-6"><div class="border-top pt-1">{{ $exam->examiner?->name }} — signature</div></div>
        <div class="col-6 text-end text-muted">Valid for 12 months from the date above.</div>
    </div>
</div>
</body>
</html>
