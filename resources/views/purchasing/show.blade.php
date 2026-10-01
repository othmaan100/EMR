@extends('layouts.app')

@section('title', 'Purchase order '.$po->po_number)

@section('content')
@php
    $user = auth()->user();
    $canReceive = $user->can('purchasing.manage') && $po->canReceive();
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="{{ route('purchasing.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Purchase orders</a>
        <h1 class="h4 mt-1 mb-1">{{ $po->po_number }} <span class="badge text-bg-{{ $po->statusColor() }} fs-6 align-middle">{{ $po->statusLabel() }}</span></h1>
        <div class="small text-muted">
            {{ $po->supplier->name }} · ordered {{ format_date($po->order_date) }}
            @if ($po->expected_date) · expected {{ format_date($po->expected_date) }}@endif
            · raised by {{ $po->creator?->name }}
            @if ($po->approved_at) · approved by {{ $po->approver?->name }}, {{ format_date($po->approved_at) }}@endif
        </div>
        @if ($po->notes)<div class="small mt-1">{{ $po->notes }}</div>@endif
        @if ($po->cancel_reason)<div class="small mt-1 text-danger">{{ $po->cancel_reason }}</div>@endif
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if ($po->status !== 'draft')
            <a href="{{ route('purchasing.print', $po) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print PO</a>
        @endif
        @if ($po->status === 'draft')
            @can('purchasing.approve')
                <form method="POST" action="{{ route('purchasing.approve', $po) }}">@csrf
                    <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i> Approve {{ money($po->total) }}</button></form>
            @endcan
        @endif
        @if (in_array($po->status, ['draft', 'approved'], true))
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reasonModal"
                    data-action="{{ route('purchasing.cancel', $po) }}" data-title="Cancel order">Cancel</button>
        @endif
        @if ($po->status === 'partially_received' && $user->can('purchasing.manage'))
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reasonModal"
                    data-action="{{ route('purchasing.close', $po) }}" data-title="Close order — no more deliveries">Close short</button>
        @endif
    </div>
</div>

@error('status')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('lines')<div class="alert alert-danger">{{ $message }}</div>@enderror

<form method="POST" action="{{ route('purchasing.receive', $po) }}">
    @csrf
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Item</th><th class="text-end">Ordered</th><th class="text-end">Unit price</th><th class="text-end">Line total</th><th class="text-end">Received</th>
                        @if ($canReceive)<th style="width: 22rem;">Delivered now</th>@endif</tr>
                </thead>
                <tbody>
                    @foreach ($po->items as $line)
                        <tr>
                            <td class="ps-3">{{ $line->description }} @if ($line->isDrug())<span class="badge text-bg-light border">Drug</span>@endif</td>
                            <td class="text-end">{{ $line->quantity }}</td>
                            <td class="text-end">{{ money($line->unit_price) }}</td>
                            <td class="text-end">{{ money($line->lineTotal()) }}</td>
                            <td class="text-end">{{ $line->quantity_received }}</td>
                            @if ($canReceive)
                                <td>
                                    @if ($line->outstanding() > 0)
                                        <div class="d-flex gap-1">
                                            <input type="number" name="lines[{{ $line->id }}][quantity]" min="0" max="{{ $line->outstanding() }}" value="{{ old("lines.{$line->id}.quantity") }}"
                                                   placeholder="of {{ $line->outstanding() }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("lines.{$line->id}.quantity")]) style="max-width: 6rem;" aria-label="Quantity delivered">
                                            @if ($line->isDrug())
                                                <input type="text" name="lines[{{ $line->id }}][batch_number]" maxlength="50" value="{{ old("lines.{$line->id}.batch_number") }}" placeholder="Batch"
                                                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has("lines.{$line->id}.batch_number")]) aria-label="Batch number">
                                                <input type="date" name="lines[{{ $line->id }}][expiry_date]" value="{{ old("lines.{$line->id}.expiry_date") }}"
                                                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has("lines.{$line->id}.expiry_date")]) aria-label="Expiry date">
                                            @endif
                                        </div>
                                        @foreach (['quantity', 'batch_number', 'expiry_date'] as $f)
                                            @error("lines.{$line->id}.{$f}")<div class="text-danger small">{{ $message }}</div>@enderror
                                        @endforeach
                                    @else
                                        <span class="small text-success"><i class="bi bi-check-circle"></i> Complete</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td class="ps-3" colspan="3">Total</td><td class="text-end">{{ money($po->total) }}</td><td class="text-end small">{{ money($po->receivedValue()) }} received</td>@if ($canReceive)<td></td>@endif</tr></tfoot>
            </table>
        </div>
        @if ($canReceive)
            <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-end justify-content-end">
                <div><label for="received_on" class="form-label small mb-1">Received on</label>
                    <input type="date" id="received_on" name="received_on" value="{{ old('received_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                <div><label for="delivery_note" class="form-label small mb-1">Delivery note / waybill no.</label>
                    <input type="text" id="delivery_note" name="delivery_note" maxlength="50" value="{{ old('delivery_note') }}" class="form-control form-control-sm"></div>
                <button class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i> Receive delivery</button>
            </div>
        @endif
    </div>
</form>

@if ($po->invoices->isNotEmpty())
    <div class="card">
        <div class="card-header">Supplier invoices</div>
        <ul class="list-group list-group-flush">
            @foreach ($po->invoices as $inv)
                <li class="list-group-item d-flex justify-content-between small">
                    <span>{{ $inv->invoice_number }} · {{ format_date($inv->invoice_date) }}</span>
                    <span>{{ money($inv->amount) }} <span class="badge text-bg-{{ $inv->status === 'paid' ? 'success' : ($inv->isOverdue() ? 'danger' : 'warning') }}">{{ $inv->status === 'paid' ? 'Paid' : ($inv->isOverdue() ? 'Overdue' : 'Unpaid') }}</span></span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="reasonForm">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="reasonTitle">Reason</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label for="reason" class="form-label">Reason</label>
                <input type="text" id="reason" name="reason" required maxlength="255" class="form-control">
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Confirm</button></div>
        </form>
    </div>
</div>
<script>
    document.getElementById('reasonModal').addEventListener('show.bs.modal', (e) => {
        document.getElementById('reasonForm').action = e.relatedTarget.dataset.action;
        document.getElementById('reasonTitle').textContent = e.relatedTarget.dataset.title;
    });
</script>
@endsection
