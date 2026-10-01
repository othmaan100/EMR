@extends('layouts.app')

@section('title', 'Requisition '.$requisition->requisition_number)

@section('content')
@php
    $canIssue = auth()->user()->can('stores.manage') && $requisition->isOpen();
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="{{ route('requisitions.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Requisitions</a>
        <h1 class="h4 mt-1 mb-1">{{ $requisition->requisition_number }} <span class="badge text-bg-{{ $requisition->statusColor() }} fs-6 align-middle">{{ $requisition->statusLabel() }}</span></h1>
        <div class="small text-muted">
            {{ $requisition->department->name }} · requested by {{ $requisition->requester?->name }}, {{ format_date($requisition->created_at, true) }}
            @if ($requisition->needed_by) · needed by {{ format_date($requisition->needed_by) }}@endif
            @if ($requisition->issued_at) · last issued {{ format_date($requisition->issued_at, true) }} by {{ $requisition->issuer?->name }}@endif
        </div>
        @if ($requisition->notes)<div class="small mt-1">{{ $requisition->notes }}</div>@endif
        @if ($requisition->closed_reason)<div class="small mt-1 text-danger">Closed: {{ $requisition->closed_reason }}</div>@endif
    </div>
    @if ($requisition->isOpen() && $requisition->requested_by === auth()->id() && $requisition->status === 'submitted')
        <form method="POST" action="{{ route('requisitions.cancel', $requisition) }}" onsubmit="return confirm('Cancel this requisition?')">
            @csrf <button class="btn btn-sm btn-outline-danger">Cancel requisition</button>
        </form>
    @endif
</div>

@error('issue')<div class="alert alert-danger">{{ $message }}</div>@enderror

<form method="POST" action="{{ route('requisitions.issue', $requisition) }}">
    @csrf
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Item</th><th class="text-end">Requested</th><th class="text-end">Issued</th>
                        @if ($canIssue)<th class="text-end">In store</th><th style="width: 10rem;">Issue now</th>@endif</tr>
                </thead>
                <tbody>
                    @foreach ($requisition->items as $line)
                        <tr>
                            <td class="ps-3">{{ $line->item->name }} <span class="small text-muted">({{ $line->item->unit }})</span></td>
                            <td class="text-end">{{ $line->quantity_requested }}</td>
                            <td class="text-end">{{ $line->quantity_issued }}</td>
                            @if ($canIssue)
                                <td @class(['text-end', 'text-danger fw-semibold' => $line->item->quantity_on_hand < $line->outstanding()])>{{ $line->item->quantity_on_hand }}</td>
                                <td>
                                    @if ($line->outstanding() > 0)
                                        <input type="number" name="issue[{{ $line->id }}]" min="0" max="{{ min($line->outstanding(), max(0, $line->item->quantity_on_hand)) }}"
                                               value="{{ old("issue.{$line->id}", min($line->outstanding(), max(0, $line->item->quantity_on_hand))) }}"
                                               @class(['form-control form-control-sm', 'is-invalid' => $errors->has("issue.{$line->id}")]) aria-label="Issue {{ $line->item->name }}">
                                        @error("issue.{$line->id}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @else
                                        <span class="small text-success"><i class="bi bi-check-circle"></i> Done</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($canIssue)
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    {{ $requisition->status === 'partially_issued' ? 'Close — no more to issue' : 'Reject' }}
                </button>
                <button class="btn btn-primary px-4"><i class="bi bi-box-arrow-up-right me-1"></i> Issue</button>
            </div>
        @endif
    </div>
</form>

@if ($canIssue)
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('requisitions.reject', $requisition) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="rejectLabel">Close requisition</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label for="reason" class="form-label">Reason (the department sees this)</label>
                    <input type="text" id="reason" name="reason" required maxlength="255" class="form-control" placeholder="e.g. Out of stock — reordered on PO…">
                </div>
                <div class="modal-footer"><button class="btn btn-danger">Close requisition</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
