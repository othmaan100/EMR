<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #e9ecef; }
        /* ID-1 / CR80 card: 85.6mm × 54mm */
        .id-card {
            width: 85.6mm; height: 54mm; background: #fff; border-radius: 3mm; overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,.15); display: flex; flex-direction: column; font-size: 7.5pt;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .id-card header { background: var(--brand); color: #fff; padding: 1.8mm 3mm; display: flex; align-items: center; gap: 2mm; }
        .id-card header img { height: 8mm; width: 8mm; object-fit: contain; background: #fff; border-radius: 1mm; }
        .id-card header .name { font-weight: 700; font-size: 8.5pt; line-height: 1.1; }
        .id-card header .sub { font-size: 6pt; opacity: .9; }
        .id-card .body { display: flex; gap: 3mm; padding: 2mm 3mm; flex: 1; }
        .id-card .photo { width: 20mm; height: 24mm; object-fit: cover; border-radius: 1mm; border: 1px solid #dee2e6; background: #f1f3f5; display: flex; align-items: center; justify-content: center; font-size: 14pt; color: #adb5bd; }
        .id-card .pname { font-weight: 700; font-size: 9pt; line-height: 1.15; margin-bottom: 1mm; }
        .id-card .hno { font-weight: 700; color: var(--brand); font-size: 10pt; }
        .id-card .row-item { line-height: 1.35; }
        .id-card footer { text-align: center; padding: 0 3mm 1.5mm; }
        .id-card footer svg { height: 9mm; max-width: 100%; }
        .id-card .note { font-size: 5.5pt; color: #6c757d; }
        @media print {
            @page { size: 85.6mm 54mm; margin: 0; }
            body { background: #fff; }
            .id-card { box-shadow: none; border-radius: 0; }
            .toolbar { display: none !important; }
            .wrap { padding: 0 !important; }
        }
    </style>
</head>
<body>
<div class="toolbar text-center py-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print card</button>
    <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
</div>
<div class="wrap d-flex justify-content-center pb-4">
    <div class="id-card">
        <header>
            @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="">@endif
            <div>
                <div class="name">{{ setting('hospital_name') }}</div>
                <div class="sub">PATIENT IDENTIFICATION CARD · {{ setting('phone') }}</div>
            </div>
        </header>
        <div class="body">
            @if ($patient->photo)
                <img class="photo" src="{{ route('patients.photo', $patient) }}" alt="">
            @else
                <div class="photo"><i class="bi bi-person"></i></div>
            @endif
            <div>
                <div class="hno">{{ $patient->hospital_number }}</div>
                <div class="pname">{{ $patient->list_name }}</div>
                <div class="row-item">Sex: <strong>{{ ucfirst($patient->gender) }}</strong> &nbsp; DOB: <strong>{{ $patient->date_of_birth ? format_date($patient->date_of_birth) : '—' }}</strong></div>
                <div class="row-item">Blood: <strong>{{ $patient->blood_group ?: '—' }}</strong> &nbsp; Genotype: <strong>{{ $patient->genotype ?: '—' }}</strong></div>
                @if ($patient->insuranceProvider)
                    <div class="row-item">{{ Str::limit($patient->insuranceProvider->name, 26) }}: <strong>{{ $patient->insurance_number }}</strong></div>
                @endif
                <div class="row-item">Issued: {{ format_date(now()) }}</div>
            </div>
        </div>
        <footer>
            <svg data-barcode="{{ $patient->hospital_number }}" data-height="30" data-show-value="false"></svg>
            <div class="note">Please bring this card on every visit.</div>
        </footer>
    </div>
</div>
</body>
</html>
