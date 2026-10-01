<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; padding: 32px; }
        .letterhead { border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .code { font-family: ui-monospace, Consolas, monospace; font-size: 2rem; letter-spacing: .2em; }
        @media print { @page { size: A5; margin: 10mm; } body { background: #fff; } .sheet { padding: 0; box-shadow: none !important; } .toolbar { display: none !important; } }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <div class="alert alert-warning d-inline-block small mb-2">This code is shown only once. Print it or read it to the patient now.</div><br>
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print</button>
    <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Back to patient</a>
</div>
<div class="sheet shadow-sm mb-4">
    @include('billing._letterhead', ['docTitle' => 'PATIENT PORTAL ACCESS', 'docNumber' => $patient->hospital_number, 'docDate' => format_date(now())])

    <p>Dear {{ $patient->full_name }},</p>
    <p>You can now see your appointments, test results, bills and receipts online.</p>

    <ol>
        <li>Go to <strong>{{ route('portal.activate') }}</strong></li>
        <li>Enter your hospital number: <strong>{{ $patient->hospital_number }}</strong></li>
        <li>Enter this activation code and choose a password:</li>
    </ol>
    <div class="text-center my-4"><span class="code border rounded px-4 py-2">{{ $code }}</span></div>
    <p class="small text-muted">The code works once and expires in {{ $hours }} hours ({{ format_date(now()->addHours($hours), true) }}).
        After that, sign in at {{ route('portal.login') }} with your hospital number and password.
        Keep your password private. If you forget it, ask the hospital's records desk for a new code.</p>
</div>
</body>
</html>
