@extends('layouts.app')

@section('title', 'Billing')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <label class="form-label fw-semibold" for="bill-search">Open a patient account</label>
        <div data-patient-picker data-url="{{ route('patients.lookup') }}" class="position-relative" id="bill-picker">
            <input type="hidden" name="patient_id" id="bill-patient-id">
            <input type="search" id="bill-search" data-picker-input autocomplete="off" class="form-control form-control-lg" placeholder="Type patient name, hospital number or phone…">
            <div data-picker-results class="list-group position-absolute w-100 shadow-sm" style="z-index: 10;"></div>
            <div data-picker-selected class="d-none"></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Patients with unpaid charges</div>
            <ul class="list-group list-group-flush">
                @forelse ($owing as $row)
                    <a href="{{ route('billing.account', $row['patient']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            {{ $row['patient']->list_name }}
                            <small class="d-block text-muted">{{ $row['patient']->hospital_number }} · {{ $row['patient']->paymentLabel() }}</small>
                        </span>
                        <span class="fw-semibold text-danger">{{ money($row['due']) }}</span>
                    </a>
                @empty
                    <li class="list-group-item text-muted small">No outstanding charges.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Cash book</span>
                <form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Date"></form>
            </div>
            <div class="card-body pb-0">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="small text-muted">Total received</div>
                        <div class="h4 mb-0">{{ money($byMethod->sum()) }}</div>
                    </div>
                    <div class="col-6 small">
                        @foreach ($byMethod as $method => $sum)
                            <div class="d-flex justify-content-between"><span>{{ \App\Models\Payment::METHODS[$method] ?? $method }}</span><span>{{ money($sum) }}</span></div>
                        @endforeach
                    </div>
                </div>
                @if ($byCashier->count() > 1)
                    <div class="small mb-2">
                        @foreach ($byCashier as $name => $sum)<span class="badge text-bg-light border me-1">{{ $name }}: {{ money($sum) }}</span>@endforeach
                    </div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm small mb-0">
                    <tbody>
                        @forelse ($payments as $p)
                            <tr @class(['text-decoration-line-through text-muted' => $p->isVoided()])>
                                <td class="ps-3"><a href="{{ route('billing.receipt', $p) }}" target="_blank">{{ $p->receipt_number }}</a></td>
                                <td>{{ $p->created_at->format('h:i A') }}</td>
                                <td>{{ $p->patient->list_name }}</td>
                                <td>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</td>
                                <td class="text-end pe-3">{{ money($p->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">No payments on this day.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Picking a patient opens their account.
    document.getElementById('bill-picker').addEventListener('click', () => {
        setTimeout(() => {
            const id = document.getElementById('bill-patient-id').value;
            if (id) window.location = @json(url('billing/patients')) + '/' + id;
        }, 0);
    });
</script>
@endsection
