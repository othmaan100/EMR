@extends('layouts.app')

@section('title', 'Account — '.$patient->hospital_number)

@section('content')
@php
    $user = auth()->user();
    $due = $unpaid->sum(fn ($i) => $i->outstanding());
@endphp

@include('patients._mini-banner', ['visit' => $openVisit])

@if (session('receipt'))
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check-circle me-1"></i> Payment recorded.</span>
        <a href="{{ route('billing.receipt', session('receipt')) }}" target="_blank" class="btn btn-sm btn-success" id="receipt-link"><i class="bi bi-printer me-1"></i> Print receipt</a>
    </div>
    <script>document.addEventListener('DOMContentLoaded', () => window.open(document.getElementById('receipt-link').href, '_blank'));</script>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Patient owes</div>
            <div class="h3 mb-0 {{ $due > 0 ? 'text-danger' : 'text-success' }}">{{ money($due) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Payment type</div>
            <div class="fw-semibold">{{ $patient->paymentLabel() }}</div>
            @if ($patient->insuranceProvider && in_array($patient->payment_type, ['insurance', 'corporate']))
                <div class="small text-muted">Covers {{ $patient->insuranceProvider->coverage_percent }}% · Member {{ $patient->insurance_number }}</div>
            @endif
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="small text-muted">Deposit available</div>
                    <div class="h5 mb-0 text-success">{{ money($deposit) }}</div>
                </div>
                @can('billing.collect')
                    <button class="btn btn-sm btn-outline-success align-self-start" data-bs-toggle="modal" data-bs-target="#depositModal"><i class="bi bi-piggy-bank me-1"></i> Take deposit</button>
                @endcan
            </div>
            @if ($credit > 0)
                <div class="small text-warning-emphasis mt-1">Refund due: {{ money($credit) }} (paid for charges later voided)</div>
            @endif
            @can('billing.collect')
                @if (app(\App\Integrations\Payments\OnlinePaymentService::class)->enabled() && $unpaid->isNotEmpty())
                    <form method="POST" action="{{ route('billing.payment-link', $patient) }}" class="mt-2 pt-2 border-top">
                        @csrf
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-link-45deg me-1"></i> Online payment link</button>
                            @if ($patient->phone)
                                <div class="form-check small mb-0">
                                    <input type="hidden" name="send_sms" value="0">
                                    <input class="form-check-input" type="checkbox" name="send_sms" value="1" id="send_link_sms" checked>
                                    <label class="form-check-label" for="send_link_sms">SMS it to {{ $patient->phone }}</label>
                                </div>
                            @endif
                        </div>
                        @if (session('payment_link'))
                            <input type="text" readonly class="form-control form-control-sm font-monospace mt-2" value="{{ session('payment_link') }}" onclick="this.select()" aria-label="Payment link">
                        @endif
                        @error('payment')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                @endif
            @endcan
        </div></div>
    </div>
</div>

{{-- ===== Unpaid items & payment ===== --}}
<form method="POST" action="{{ route('billing.pay', $patient) }}" id="pay-form">
    @csrf
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cash-coin me-1"></i> Unpaid charges</span>
            @if ($unpaid->isNotEmpty())
                <div class="form-check mb-0 small fw-normal">
                    <input class="form-check-input" type="checkbox" id="select-all" checked>
                    <label class="form-check-label" for="select-all">Select all</label>
                </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3" style="width: 2rem;"></th><th>Date</th><th>Item</th><th class="text-end">Charge</th><th class="text-end">Insurance</th><th class="text-end">Discount</th><th class="text-end">Paid</th><th class="text-end">Due</th><th></th></tr></thead>
                <tbody>
                    @forelse ($unpaid as $item)
                        <tr>
                            <td class="ps-3"><input class="form-check-input" type="checkbox" name="items[]" value="{{ $item->id }}" data-due="{{ $item->outstanding() }}" checked aria-label="Pay {{ $item->description }}"></td>
                            <td class="small text-nowrap">{{ format_date($item->created_at) }}</td>
                            <td>{{ $item->description }} <small class="text-muted">{{ $item->bill->bill_number }}</small>
                                @if ($item->discount_reason)<small class="d-block text-muted">Discount: {{ $item->discount_reason }}</small>@endif</td>
                            <td class="text-end">{{ money($item->amount) }}</td>
                            <td class="text-end text-muted">{{ $item->insurance_amount ? money($item->insurance_amount) : '—' }}</td>
                            <td class="text-end text-muted">{{ $item->discount_amount ? money($item->discount_amount) : '—' }}</td>
                            <td class="text-end text-muted">{{ $item->paid_amount ? money($item->paid_amount) : '—' }}</td>
                            <td class="text-end fw-semibold">{{ money($item->outstanding()) }}</td>
                            <td class="text-end pe-3 text-nowrap">
                                @can('billing.discount')
                                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#discountModal"
                                            data-action="{{ route('billing.discount', $item) }}" data-name="{{ $item->description }}" data-max="{{ $item->patient_amount - $item->paid_amount }}" title="Discount / waive"><i class="bi bi-percent"></i></button>
                                    @if ($item->paid_amount == 0)
                                        <button type="button" class="btn btn-sm btn-light text-danger" data-bs-toggle="modal" data-bs-target="#voidModal"
                                                data-action="{{ route('billing.void-item', $item) }}" data-name="{{ $item->description }}" title="Void charge"><i class="bi bi-x-lg"></i></button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-check2-circle me-1"></i>Nothing owed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($unpaid->isNotEmpty() && $user->can('billing.collect'))
            <div class="card-footer bg-white">
                @if ($errors->hasAny(['items', 'amount', 'method', 'reference']))
                    <div class="alert alert-danger py-2 small">{{ $errors->first('items') ?: $errors->first('amount') ?: $errors->first('method') ?: $errors->first('reference') }}</div>
                @endif
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="amount" class="form-label small mb-1">Amount received</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ setting('currency_symbol') }}</span>
                            <input type="number" step="0.01" min="0.01" id="amount" name="amount" required class="form-control fw-semibold" value="{{ old('amount', number_format($due, 2, '.', '')) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="method" class="form-label small mb-1">Method</label>
                        <select id="method" name="method" class="form-select">
                            @foreach (\App\Models\Payment::cashierMethods() as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach
                            @if ($deposit > 0)<option value="deposit" @selected(old('method') === 'deposit')>From deposit ({{ money($deposit) }})</option>@endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="reference" class="form-label small mb-1">Reference (non-cash)</label>
                        <input type="text" id="reference" name="reference" maxlength="100" class="form-control" value="{{ old('reference') }}" placeholder="POS / transfer ref.">
                    </div>
                    <div class="col-md-3"><button class="btn btn-success w-100"><i class="bi bi-cash-coin me-1"></i> Receive payment</button></div>
                </div>
                <div class="small text-muted mt-1">Selected: <strong id="selected-due">{{ money($due) }}</strong>. Part payments are applied to the ticked items oldest first.</div>
            </div>
        @endif
    </div>
</form>

@can('billing.collect')
    @if ($services->isNotEmpty())
        <form method="POST" action="{{ route('billing.charge', $patient) }}" class="card mb-3">
            @csrf
            <div class="card-body row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="service_id" class="form-label small mb-1">Add a service charge {{ $openVisit ? 'to the current visit' : '' }}</label>
                    <select id="service_id" name="service_id" class="form-select" required>
                        <option value="">Select service…</option>
                        @foreach ($services->groupBy('category') as $category => $group)
                            <optgroup label="{{ $category }}">
                                @foreach ($group as $s)<option value="{{ $s->id }}">{{ $s->name }} — {{ money($s->priceFor()) }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><label for="quantity" class="form-label small mb-1">Qty</label><input type="number" id="quantity" name="quantity" value="1" min="0.01" step="0.01" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add charge</button></div>
            </div>
        </form>
    @endif
@endcan

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Bills</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Bill</th><th>Date</th><th>Visit</th><th class="text-end">Total</th><th class="text-end">Insurance</th><th class="text-end">Balance</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($bills as $bill)
                            @php $t = $bill->totals(); @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $bill->bill_number }}</td>
                                <td class="small">{{ format_date($bill->created_at) }}</td>
                                <td class="small">{{ $bill->visit ? $bill->visit->clinic->name : 'Registration / other' }}</td>
                                <td class="text-end">{{ money($t['amount']) }}</td>
                                <td class="text-end small">
                                    {{ $t['insurance'] ? money($t['insurance']) : '—' }}
                                    @if ($bill->claim_status !== 'none')<span class="badge text-bg-{{ \App\Models\Bill::CLAIM_STATUSES[$bill->claim_status]['color'] }}">{{ \App\Models\Bill::CLAIM_STATUSES[$bill->claim_status]['label'] }}</span>@endif
                                    @if ($bill->authorization_code)<span class="d-block text-muted">PA {{ $bill->authorization_code }}</span>@endif
                                </td>
                                <td class="text-end fw-semibold {{ $t['balance'] > 0 ? 'text-danger' : 'text-success' }}">{{ money($t['balance']) }}</td>
                                <td class="text-end pe-3"><a href="{{ route('billing.invoice', $bill) }}" target="_blank" class="btn btn-sm btn-light" title="Invoice"><i class="bi bi-printer"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No bills yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        @if ($patient->insurance_provider_id && in_array($patient->payment_type, ['insurance', 'corporate'], true))
            @can('claims.preauth')
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Pre-authorisation (PA code)</span>
                        <a href="{{ route('preauth.index') }}" class="small">All requests</a>
                    </div>
                    <div class="card-body">
                        @foreach ($preauths as $pa)
                            <div class="small mb-2 pb-2 border-bottom">
                                <span class="badge text-bg-{{ \App\Models\Preauthorization::STATUSES[$pa->status]['color'] }}">{{ \App\Models\Preauthorization::STATUSES[$pa->status]['label'] }}</span>
                                @if ($pa->code)<strong class="ms-1">{{ $pa->code }}</strong>@endif
                                · {{ format_date($pa->created_at) }} @if ($pa->bill)· {{ $pa->bill->bill_number }}@endif
                                <div class="text-muted">{{ Str::limit($pa->services, 90) }}</div>
                            </div>
                        @endforeach
                        <form method="POST" action="{{ route('preauth.store', $patient) }}">
                            @csrf
                            <div class="mb-2">
                                <label for="pa_services" class="form-label small mb-1">Services needing approval from {{ $patient->insuranceProvider?->name }}</label>
                                <textarea id="pa_services" name="services" rows="2" maxlength="2000" required class="form-control form-control-sm" placeholder="e.g. CT scan brain, admission 3 days">{{ old('services') }}</textarea>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-7"><input type="text" name="diagnosis" maxlength="255" value="{{ old('diagnosis') }}" class="form-control form-control-sm" placeholder="Diagnosis" aria-label="Diagnosis"></div>
                                <div class="col-5"><input type="number" name="amount_requested" min="0" step="0.01" value="{{ old('amount_requested') }}" class="form-control form-control-sm" placeholder="Est. cost" aria-label="Estimated cost"></div>
                            </div>
                            <div class="d-flex gap-2">
                                <select name="bill_id" class="form-select form-select-sm" aria-label="Attach to bill">
                                    <option value="">Attach to bill (optional)</option>
                                    @foreach ($bills->whereIn('claim_status', ['pending', 'rejected'])->take(10) as $b)
                                        <option value="{{ $b->id }}">{{ $b->bill_number }} · {{ format_date($b->created_at) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary text-nowrap">Request PA</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
        <div class="card">
            <div class="card-header">Payments</div>
            <ul class="list-group list-group-flush">
                @forelse ($payments as $p)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2 small">
                        <span @class(['text-decoration-line-through text-muted' => $p->isVoided()])>
                            <a href="{{ route('billing.receipt', $p) }}" target="_blank" class="fw-semibold">{{ $p->receipt_number }}</a>
                            · {{ format_date($p->created_at, true) }} · {{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}
                            <span class="d-block text-muted">{{ $p->receiver?->name }}</span>
                        </span>
                        <span class="text-end text-nowrap">
                            <strong>{{ money($p->amount) }}</strong>
                            @if ($p->isVoided())
                                <span class="d-block badge text-bg-secondary" title="{{ $p->void_reason }}">Reversed</span>
                            @else
                                @can('billing.reverse')
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0 d-block" data-bs-toggle="modal" data-bs-target="#reverseModal"
                                            data-action="{{ route('billing.reverse', $p) }}" data-name="{{ $p->receipt_number }}">Reverse</button>
                                @endcan
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="list-group-item small text-muted">No payments yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- Reason modals (discount / void / reverse) share one pattern. --}}
@foreach ([
    'discountModal' => ['Discount / waiver', 'discount'],
    'voidModal' => ['Void charge', 'void'],
    'reverseModal' => ['Reverse payment', 'reverse'],
] as $id => [$title, $kind])
    <div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}: <span data-name></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($kind === 'discount')
                        <label class="form-label" for="discount_amount">Discount amount (max <span data-max></span>)</label>
                        <input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount" class="form-control mb-3" required>
                        <label class="form-label" for="discount_reason">Reason</label>
                        <input type="text" id="discount_reason" name="discount_reason" class="form-control" required maxlength="255" placeholder="e.g. Social welfare, staff relative, MD approval">
                    @else
                        <label class="form-label" for="{{ $kind }}_reason">Reason</label>
                        <input type="text" id="{{ $kind }}_reason" name="void_reason" class="form-control" required maxlength="255"
                               placeholder="{{ $kind === 'reverse' ? 'e.g. Wrong patient, duplicate payment' : 'e.g. Charged in error' }}">
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn {{ $kind === 'discount' ? 'btn-primary' : 'btn-danger' }}">{{ $title }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@error('discount_amount', 'discount')<div class="alert alert-danger">{{ $message }}</div>@enderror

@can('billing.collect')
    <div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('billing.deposit', $patient) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="depositLabel">Take a deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Held as credit on the patient's account and used to pay charges later (choose "From deposit" when paying).</p>
                    <label for="dep_amount" class="form-label">Amount</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text">{{ setting('currency_symbol') }}</span>
                        <input type="number" step="0.01" min="0.01" id="dep_amount" name="amount" class="form-control" required>
                    </div>
                    <label for="dep_method" class="form-label">Method</label>
                    <select id="dep_method" name="method" class="form-select mb-3">
                        @foreach (\App\Models\Payment::cashierMethods() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                    <label for="dep_ref" class="form-label">Reference (non-cash)</label>
                    <input type="text" id="dep_ref" name="reference" maxlength="100" class="form-control">
                    @if ($errors->deposit->any())<div class="text-danger small mt-2">{{ $errors->deposit->first() }}</div>@endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Receive deposit</button>
                </div>
            </form>
        </div>
    </div>
    @if ($errors->deposit->any())
        <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal('#depositModal').show());</script>
    @endif
@endcan

<script>
    document.querySelectorAll('#discountModal, #voidModal, #reverseModal').forEach((modal) => {
        modal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            modal.querySelector('form').action = btn.dataset.action;
            modal.querySelector('[data-name]').textContent = btn.dataset.name;
            const max = modal.querySelector('[data-max]');
            if (max) {
                max.textContent = Number(btn.dataset.max).toFixed(2);
                modal.querySelector('[name=discount_amount]').max = btn.dataset.max;
            }
        });
    });

    // Keep the amount in step with the ticked items.
    const boxes = document.querySelectorAll('#pay-form input[name="items[]"]');
    const amount = document.getElementById('amount');
    const selectedDue = document.getElementById('selected-due');
    const recalc = () => {
        const total = [...boxes].filter((b) => b.checked).reduce((s, b) => s + parseFloat(b.dataset.due), 0);
        if (amount) amount.value = total.toFixed(2);
        if (selectedDue) selectedDue.textContent = @json(setting('currency_symbol')) + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    boxes.forEach((b) => b.addEventListener('change', recalc));
    document.getElementById('select-all')?.addEventListener('change', (e) => {
        boxes.forEach((b) => (b.checked = e.target.checked));
        recalc();
    });
</script>
@endsection
