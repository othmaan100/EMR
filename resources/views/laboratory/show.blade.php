@extends('layouts.app')

@section('title', 'Lab Order '.$order->order_number)

@section('content')
@php
    $editable = in_array($order->status, ['collected', 'in_progress'], true);
    $activeItems = $order->items->where('status', '!=', 'cancelled');
@endphp

@include('patients._mini-banner')

@if ($order->rejection_reason && $order->status === 'requested')
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> Previous sample rejected: <strong>{{ $order->rejection_reason }}</strong>. Please recollect.</div>
@endif

<div class="row g-3">
    <div class="col-lg-4 order-lg-2">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $order->order_number }}</span>
                <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Priority</dt>
                    <dd class="col-7">@if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@else Routine @endif</dd>
                    <dt class="col-5 text-muted fw-normal">Requested by</dt><dd class="col-7">{{ $order->orderedBy?->name }}</dd>
                    <dt class="col-5 text-muted fw-normal">Requested</dt><dd class="col-7">{{ format_date($order->created_at, true) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Clinic</dt><dd class="col-7">{{ $order->visit?->clinic?->name ?? '—' }}</dd>
                    @if ($order->collected_at)
                        <dt class="col-5 text-muted fw-normal">Collected</dt>
                        <dd class="col-7">{{ format_date($order->collected_at, true) }}<br>{{ $order->collectedBy?->name }}</dd>
                    @endif
                    @if ($order->completed_at)
                        <dt class="col-5 text-muted fw-normal">Released</dt><dd class="col-7">{{ format_date($order->completed_at, true) }}</dd>
                    @endif
                </dl>
                @if ($order->clinical_notes)
                    <div class="border-top pt-2 mt-2"><span class="text-muted">Clinical notes:</span> {{ $order->clinical_notes }}</div>
                @endif
            </div>
            <div class="card-footer bg-white d-grid gap-2">
                @if ($order->status === 'requested')
                    <form method="POST" action="{{ route('lab.collect', $order) }}" class="d-grid">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-droplet me-1"></i> Mark sample collected</button>
                    </form>
                @endif
                @if ($order->collected_at)
                    <a href="{{ route('lab.label', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-upc me-1"></i> Print sample label</a>
                @endif
                @if ($order->status === 'in_progress')
                    @can('lab.verify')
                        <form method="POST" action="{{ route('lab.verify', $order) }}" class="d-grid"
                              onsubmit="return confirm('Verify and release these results to the clinicians?')">
                            @csrf
                            <button class="btn btn-success"><i class="bi bi-patch-check me-1"></i> Verify & release</button>
                        </form>
                    @endcan
                @endif
                @if (in_array($order->status, ['in_progress', 'completed'], true))
                    <a href="{{ route('lab.report', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> {{ $order->status === 'completed' ? 'Print report' : 'Preview report' }}</a>
                @endif
                @if ($editable)
                    <button class="btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject sample…</button>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8 order-lg-1">
        @if ($editable)
            <form method="POST" action="{{ route('lab.results', $order) }}">
                @csrf
                @foreach ($activeItems as $item)
                    @php
                        $existing = $item->results->keyBy(fn ($r) => $r->lab_test_parameter_id ?? 'text');
                    @endphp
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>{{ $item->test->name }} <small class="text-muted fw-normal">{{ $item->test->sample_type }}</small></span>
                            @if ($item->status === 'resulted')
                                <small class="text-success fw-normal"><i class="bi bi-check-circle"></i> Entered by {{ $item->enteredBy?->name }}, {{ $item->entered_at?->format('h:i A') }}</small>
                            @endif
                        </div>
                        <div class="card-body">
                            @if ($item->test->parameters->isEmpty())
                                <textarea name="results[{{ $item->id }}][text]" rows="3" class="form-control" aria-label="{{ $item->test->name }} result"
                                          placeholder="Result">{{ old("results.{$item->id}.text", $existing->get('text')?->value) }}</textarea>
                            @else
                                <div class="row g-2">
                                    @foreach ($item->test->parameters as $p)
                                        @php
                                            $field = "results.{$item->id}.{$p->id}";
                                            $value = old($field, $existing->get($p->id)?->value);
                                        @endphp
                                        <div class="col-md-6">
                                            <label for="r-{{ $item->id }}-{{ $p->id }}" class="form-label small mb-1">
                                                {{ $p->name }} @if ($p->referenceLabel())<span class="text-muted">({{ $p->referenceLabel() }})</span>@endif
                                            </label>
                                            <div class="input-group input-group-sm">
                                                @if ($p->type === 'option')
                                                    <select id="r-{{ $item->id }}-{{ $p->id }}" name="results[{{ $item->id }}][{{ $p->id }}]"
                                                            @class(['form-select', 'is-invalid' => $errors->has($field)])>
                                                        <option value="">—</option>
                                                        @foreach ($p->options ?? [] as $opt)
                                                            <option @selected($value === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="{{ $p->type === 'numeric' ? 'text' : 'text' }}" id="r-{{ $item->id }}-{{ $p->id }}"
                                                           name="results[{{ $item->id }}][{{ $p->id }}]" value="{{ $value }}"
                                                           @if ($p->type === 'numeric') inputmode="decimal" data-ref-low="{{ $p->ref_low }}" data-ref-high="{{ $p->ref_high }}" @endif
                                                           @class(['form-control', 'is-invalid' => $errors->has($field)])>
                                                @endif
                                                @if ($p->unit)<span class="input-group-text">{{ $p->unit }}</span>@endif
                                            </div>
                                            @error($field)<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <input type="text" name="comments[{{ $item->id }}]" value="{{ old("comments.{$item->id}", $item->comment) }}"
                                   class="form-control form-control-sm mt-2" placeholder="Comment (optional)" aria-label="Comment">
                        </div>
                    </div>
                @endforeach
                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save results</button>
                </div>
            </form>
        @elseif ($order->status === 'completed')
            <div class="card mb-3">
                <div class="card-header">Released results</div>
                <div class="table-responsive">@include('laboratory._results')</div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-droplet fs-1 d-block mb-2"></i>
                    Collect the sample to start entering results for:
                    <div class="fw-semibold mt-1">{{ $activeItems->map(fn ($i) => $i->test->name)->implode(', ') }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

@if ($editable)
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('lab.reject', $order) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectLabel">Reject sample</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Any results entered will be discarded and the order returned to "To collect".</p>
                    <label for="reason" class="form-label">Reason</label>
                    <select id="reason" name="reason" class="form-select" required>
                        @foreach (['Haemolysed', 'Clotted', 'Insufficient volume', 'Wrong container', 'Unlabelled / mislabelled', 'Delayed in transit'] as $r)
                            <option>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger">Reject sample</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
