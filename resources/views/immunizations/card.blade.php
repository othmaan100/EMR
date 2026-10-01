<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; padding: 28px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 10px; margin-bottom: 14px; }
        @media print { @page { size: A5; margin: 8mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'VACCINATION CARD', 'docNumber' => $patient->hospital_number, 'docDate' => 'Printed '.format_date(now())])

    <table class="table table-sm table-borderless small mb-2">
        <tr><td class="text-muted" style="width: 22%">Child</td><td class="fw-semibold">{{ $patient->full_name }}</td>
            <td class="text-muted" style="width: 18%">Sex</td><td>{{ ucfirst($patient->gender) }}</td></tr>
        <tr><td class="text-muted">Date of birth</td><td>{{ $patient->date_of_birth ? format_date($patient->date_of_birth) : '—' }}</td>
            <td class="text-muted">Carer</td><td>{{ $patient->nok_name ?? '—' }}</td></tr>
    </table>

    <table class="table table-sm table-bordered small align-middle">
        <thead class="table-light"><tr><th>Age</th><th>Vaccine</th><th>Due</th><th>Given on</th><th>Batch</th></tr></thead>
        <tbody>
            @foreach ($schedule as $row)
                <tr>
                    <td>{{ $row['vaccine']->ageLabel() }}</td>
                    <td>{{ $row['vaccine']->label }}</td>
                    <td>{{ $row['due'] ? format_date($row['due']) : '' }}</td>
                    <td class="fw-semibold">{{ $row['record'] ? format_date($row['record']->given_on) : '' }}</td>
                    <td>{{ $row['record']?->batch_number }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small text-muted">Bring this card to every clinic visit.</div>
</div>
</body>
</html>
