@extends('layouts.app')

@section('title', 'Claim batch '.$batch->batch_number)

@section('content')
@php
    $draft = $batch->status === 'draft';
    $canRemit = in_array($batch->status, ['submitted', 'reconciled'], true);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="{{ route('billing.claims', ['tab' => 'batches']) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All batches</a>
        <h1 class="h4 mb-1 mt-1">{{ $batch->batch_number }} <span class="badge text-bg-{{ $batch->statusColor() }} fs-6 align-middle">{{ $batch->statusLabel() }}</span></h1>
        <div class="small text-muted">
            {{ $batch->insuranceProvider->name }} · services {{ format_date($batch->period_from) }} – {{ format_date($batch->period_to) }}
            · created by {{ $batch->creator?->name }}
            @if ($batch->submitted_at) · submitted {{ format_date($batch->submitted_at) }} by {{ $batch->submitter?->name }}@endif
        </div>
        @if ($batch->notes)<div class="small mt-1">{{ $batch->notes }}</div>@endif
        @if ($batch->submission_status)
            <div class="small mt-1"><span class="badge text-bg-{{ $batch->submission_status === 'error' ? 'danger' : 'info' }}">e-Claim: {{ $batch->submission_status }}</span>
                @if ($batch->submitted_electronically_at) sent {{ format_date($batch->submitted_electronically_at, true) }}@endif
                @if ($batch->submission_reference) · ref {{ $batch->submission_reference }}@endif</div>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('billing.claims.print', $batch) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print schedule</a>
        <a href="{{ route('billing.claims.export', $batch) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i> Export CSV</a>
        <a href="{{ route('billing.claims.electronic', $batch) }}" class="btn btn-sm btn-outline-secondary" title="Structured claim file (JSON)"><i class="bi bi-filetype-json me-1"></i> e-Claim file</a>
        @if ($batch->status !== 'draft' && app(\App\Integrations\Claims\ElectronicClaimService::class)->configured())
            <form method="POST" action="{{ route('billing.claims.send', $batch) }}" onsubmit="return confirm('Send this batch to the claims service now?')">
                @csrf
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-send-check me-1"></i> {{ $batch->submitted_electronically_at ? 'Send again' : 'Send electronically' }}</button>
            </form>
        @endif
        @if ($draft)
            <form method="POST" action="{{ route('billing.claims.batches.destroy', $batch) }}" onsubmit="return confirm('Delete this draft? Its bills go back to “To batch”.')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Delete draft</button>
            </form>
            <form method="POST" action="{{ route('billing.claims.submit', $batch) }}" onsubmit="return confirm('Mark this batch as submitted to {{ e($batch->insuranceProvider->name) }}? Bills can no longer be changed.')">
                @csrf
                <button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i> Mark submitted</button>
            </form>
        @endif
    </div>
</div>

@error('batch')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row g-3 mb-3">
    @foreach ([['Claimed', $batch->amount_claimed], ['Paid', $batch->amount_paid], ['Awaiting decision', $batch->outstanding()],
               ['Shortfall', $batch->bills->sum(fn ($b) => $b->claimShortfall())]] as [$label, $value])
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">{{ $label }}</div>
                <div class="fs-5 fw-semibold">{{ money($value) }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('billing.claims.remit', $batch) }}">
    @csrf
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bill</th><th>Patient</th><th>Member no.</th><th>PA code</th><th class="text-end">Claimed</th>
                        @if ($canRemit)<th style="width: 9rem;">Paid by HMO</th><th>Shortfall reason</th>@endif
                        <th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batch->bills as $bill)
                        @php
                            $current = $bill->totals()['insurance'];
                            $locked = (bool) $bill->claim_transferred_at;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('billing.invoice', $bill) }}" target="_blank">{{ $bill->bill_number }}</a>
                                <small class="d-block text-muted">{{ format_date($bill->created_at) }} · {{ $bill->admission_id ? 'Inpatient' : $bill->visit?->clinic?->name }}</small>
                            </td>
                            <td>{{ $bill->patient->list_name }}<small class="d-block text-muted">{{ $bill->patient->hospital_number }}</small></td>
                            <td class="small">{{ $bill->patient->insurance_number }}</td>
                            <td class="small">{{ $bill->authorization_code ?? '—' }}</td>
                            <td class="text-end fw-semibold">
                                {{ money($bill->claim_amount) }}
                                @if (! $draft && abs($current - $bill->claim_amount) > 0.004 && ! $locked)
                                    <span class="d-block small text-warning-emphasis" title="Charges changed after submission">now {{ money($current) }}</span>
                                @endif
                            </td>
                            @if ($canRemit)
                                <td>
                                    <input type="number" step="0.01" min="0" max="{{ $bill->claim_amount }}" name="lines[{{ $bill->id }}][paid]" @disabled($locked)
                                           value="{{ old("lines.{$bill->id}.paid", $bill->claim_status === 'submitted' ? '' : $bill->claim_amount_paid) }}"
                                           @class(['form-control form-control-sm', 'is-invalid' => $errors->has("lines.{$bill->id}.paid")]) aria-label="Paid for {{ $bill->bill_number }}">
                                    @error("lines.{$bill->id}.paid")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="text" maxlength="255" name="lines[{{ $bill->id }}][reason]" @disabled($locked) list="rejection-reasons"
                                           value="{{ old("lines.{$bill->id}.reason", $bill->claim_rejection_reason) }}"
                                           @class(['form-control form-control-sm', 'is-invalid' => $errors->has("lines.{$bill->id}.reason")]) aria-label="Reason for {{ $bill->bill_number }}">
                                    @error("lines.{$bill->id}.reason")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                            @endif
                            <td>
                                <span class="badge text-bg-{{ \App\Models\Bill::CLAIM_STATUSES[$bill->claim_status]['color'] }}">{{ \App\Models\Bill::CLAIM_STATUSES[$bill->claim_status]['label'] }}</span>
                                @if ($locked)<span class="d-block small text-muted">Shortfall billed to patient</span>@endif
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('billing.claims.form', $bill) }}" target="_blank" class="btn btn-sm btn-link" title="Claim form"><i class="bi bi-file-earmark-text"></i></a>
                                @if ($draft)
                                    <button form="remove-{{ $bill->id }}" class="btn btn-sm btn-link text-danger" title="Remove from batch"><i class="bi bi-x-lg"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($canRemit)
            <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label for="paid_on" class="form-label small mb-1">Paid on</label>
                    <input type="date" id="paid_on" name="paid_on" max="{{ today()->toDateString() }}" value="{{ old('paid_on', $batch->paid_on?->toDateString()) }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="payment_reference" class="form-label small mb-1">Remittance / transfer ref.</label>
                    <input type="text" id="payment_reference" name="payment_reference" maxlength="100" value="{{ old('payment_reference', $batch->payment_reference) }}" class="form-control form-control-sm">
                </div>
                <button class="btn btn-sm btn-success"><i class="bi bi-check2-all me-1"></i> Save remittance</button>
                <span class="small text-muted">Enter what the HMO paid for each bill (0 = rejected). Leave blank if not yet decided.</span>
            </div>
        @endif
    </div>
</form>

<datalist id="rejection-reasons">
    @foreach (['No PA code', 'Service not covered', 'Tariff above agreed price', 'Enrollee not eligible / expired', 'Duplicate claim', 'Incomplete documentation', 'Late submission'] as $r)
        <option value="{{ $r }}">
    @endforeach
</datalist>

@if ($draft)
    @foreach ($batch->bills as $bill)
        <form method="POST" action="{{ route('billing.claims.remove-bill', [$batch, $bill]) }}" id="remove-{{ $bill->id }}">@csrf @method('DELETE')</form>
    @endforeach
@endif
@endsection
