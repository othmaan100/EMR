@extends('layouts.app')

@section('title', 'Insurance / HMO Claims')

@section('content')
@php
    $tabs = [
        'unbatched' => ['To batch', 'bi-inbox'],
        'batches' => ['Batches', 'bi-collection'],
        'rejected' => ['Rejected & short-paid', 'bi-exclamation-octagon'],
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        @foreach ($tabs as $key => [$label, $icon])
            <li class="nav-item">
                <a href="{{ route('billing.claims', array_filter(['tab' => $key, 'provider_id' => $filters['provider_id'] ?? null])) }}" @class(['nav-link', 'active' => $tab === $key])>
                    <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                    @if ($counts[$key])<span class="badge rounded-pill text-bg-light border ms-1">{{ $counts[$key] }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <select name="provider_id" class="form-select form-select-sm" aria-label="Insurer" onchange="this.form.submit()">
            <option value="">All insurers / companies</option>
            @foreach ($providers as $id => $name)<option value="{{ $id }}" @selected(($filters['provider_id'] ?? null) == $id)>{{ $name }}</option>@endforeach
        </select>
        @can('claims.preauth')
            <a href="{{ route('preauth.index') }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-shield-check me-1"></i> PA codes</a>
        @endcan
    </form>
</div>

@error('bills')<div class="alert alert-danger">{!! implode('<br>', array_map('e', $errors->get('bills'))) !!}</div>@enderror
@error('authorization_code')<div class="alert alert-danger">{{ $message }}</div>@enderror

@if ($tab === 'unbatched')
    @forelse ($groups as $providerId => $bills)
        @php $provider = $bills->first()->insuranceProvider; @endphp
        <form method="POST" action="{{ route('billing.claims.batches.store') }}" id="batch-{{ $providerId }}">
            @csrf
            <input type="hidden" name="insurance_provider_id" value="{{ $providerId }}">
        </form>
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ $provider?->name }}
                    @if ($provider?->requires_authorization)<span class="badge text-bg-light border ms-1">PA code required</span>@endif
                </span>
                <span class="small">{{ $bills->count() }} {{ Str::plural('bill', $bills->count()) }} · {{ money($bills->sum(fn ($b) => $b->totals()['insurance'])) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3"><input class="form-check-input" type="checkbox" aria-label="Select all ready bills"
                                onclick="document.querySelectorAll('[form=batch-{{ $providerId }}][name=\'bills[]\']:not(:disabled)').forEach(b => b.checked = this.checked)"></th>
                            <th>Bill</th><th>Date</th><th>Patient</th><th>Member no.</th><th>Encounter</th><th>PA code</th><th class="text-end">Claim</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bills as $bill)
                            @php $blocker = $blockers[$bill->id]; @endphp
                            <tr>
                                <td class="ps-3"><input class="form-check-input" type="checkbox" form="batch-{{ $providerId }}" name="bills[]" value="{{ $bill->id }}"
                                    @disabled($blocker) aria-label="Select {{ $bill->bill_number }}"></td>
                                <td><a href="{{ route('billing.invoice', $bill) }}" target="_blank">{{ $bill->bill_number }}</a></td>
                                <td class="small">{{ format_date($bill->created_at) }}</td>
                                <td>{{ $bill->patient->list_name }}<small class="d-block text-muted">{{ $bill->patient->hospital_number }}</small></td>
                                <td class="small">
                                    {{ $bill->patient->insurance_number }}
                                    @if ($bill->patient->insurance_expiry && $bill->patient->insurance_expiry->lt($bill->created_at->startOfDay()))
                                        <span class="badge text-bg-danger d-block mt-1">Expired {{ format_date($bill->patient->insurance_expiry) }}</span>
                                    @endif
                                </td>
                                <td class="small">{{ $bill->admission_id ? 'Inpatient' : ($bill->visit?->clinic?->name ?? '—') }}</td>
                                <td style="min-width: 11rem;">
                                    <form method="POST" action="{{ route('billing.claims.pa-code', $bill) }}" class="input-group input-group-sm">
                                        @csrf
                                        <input type="text" name="authorization_code" value="{{ $bill->authorization_code }}" maxlength="50" class="form-control" aria-label="PA code for {{ $bill->bill_number }}" placeholder="PA code">
                                        <button class="btn btn-outline-secondary" title="Save PA code"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                </td>
                                <td class="text-end fw-semibold">{{ money($bill->totals()['insurance']) }}</td>
                                <td class="small">
                                    @if ($blocker)<span class="badge text-bg-warning">{{ $blocker }}</span>@endif
                                    <a href="{{ route('billing.claims.form', $bill) }}" target="_blank" class="ms-1" title="Claim form"><i class="bi bi-file-earmark-text"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="notes" form="batch-{{ $providerId }}" maxlength="1000" class="form-control form-control-sm" style="max-width: 24rem;" placeholder="Batch note (optional)" aria-label="Batch note">
                <button form="batch-{{ $providerId }}" class="btn btn-sm btn-primary"><i class="bi bi-collection me-1"></i> Create batch from selected</button>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-5">No insurer shares are waiting to be claimed.</div></div>
    @endforelse

@elseif ($tab === 'batches')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="tab" value="batches">
                @if ($filters['provider_id'] ?? null)<input type="hidden" name="provider_id" value="{{ $filters['provider_id'] }}">@endif
                <select name="status" class="form-select form-select-sm w-auto" aria-label="Status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\ClaimBatch::STATUSES as $key => $s)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $s['label'] }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Batch</th><th>Insurer</th><th>Period</th><th class="text-center">Bills</th><th class="text-end">Claimed</th><th class="text-end">Paid</th><th>Status</th><th>Submitted</th></tr>
                </thead>
                <tbody>
                    @forelse ($batches as $b)
                        <tr>
                            <td class="ps-3"><a href="{{ route('billing.claims.batch', $b) }}" class="fw-semibold">{{ $b->batch_number }}</a></td>
                            <td class="small">{{ $b->insuranceProvider->name }}</td>
                            <td class="small">{{ format_date($b->period_from) }} – {{ format_date($b->period_to) }}</td>
                            <td class="text-center">{{ $b->bills_count }}</td>
                            <td class="text-end">{{ money($b->amount_claimed) }}</td>
                            <td class="text-end">{{ money($b->amount_paid) }}</td>
                            <td><span class="badge text-bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                            <td class="small">
                                {{ $b->submitted_at ? format_date($b->submitted_at) : '—' }}
                                @if ($b->status === 'submitted')<span class="text-muted d-block">{{ (int) $b->submitted_at->diffInDays(now()) }} days ago</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())<div class="card-footer bg-white">{{ $batches->links() }}</div>@endif
    </div>

@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Bill</th><th>Patient</th><th>Insurer</th><th>Batch</th><th class="text-end">Claimed</th><th class="text-end">Paid</th><th class="text-end">Shortfall</th><th>Reason</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        <tr>
                            <td class="ps-3"><a href="{{ route('billing.invoice', $bill) }}" target="_blank">{{ $bill->bill_number }}</a></td>
                            <td>{{ $bill->patient->list_name }}<small class="d-block text-muted">{{ $bill->patient->hospital_number }}</small></td>
                            <td class="small">{{ $bill->insuranceProvider?->name }}</td>
                            <td class="small">@if ($bill->claimBatch)<a href="{{ route('billing.claims.batch', $bill->claimBatch) }}">{{ $bill->claimBatch->batch_number }}</a>@endif</td>
                            <td class="text-end">{{ money($bill->claim_amount) }}</td>
                            <td class="text-end">{{ money($bill->claim_amount_paid) }}</td>
                            <td class="text-end fw-semibold text-danger">{{ money($bill->claimShortfall()) }}</td>
                            <td class="small">{{ $bill->claim_rejection_reason }}</td>
                            <td class="text-end pe-3 text-nowrap">
                                @if ($bill->claim_status === 'rejected')
                                    <form method="POST" action="{{ route('billing.claims.requeue', $bill) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-primary" title="Correct and claim again">Resubmit</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('billing.claims.transfer', $bill) }}" class="d-inline"
                                      onsubmit="return confirm('Move {{ money($bill->claimShortfall()) }} to the patient\'s own account?')">@csrf
                                    <button class="btn btn-sm btn-outline-danger">Bill patient</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No rejected or short-paid claims.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            <strong>Resubmit</strong> returns a fully rejected bill to “To batch” (e.g. after adding a missing PA code).
            <strong>Bill patient</strong> moves the unpaid insurer share to the patient's own balance. Leave a line here to write it off.
        </div>
    </div>
@endif
@endsection
