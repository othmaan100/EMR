@extends('layouts.app')

@section('title', 'Imaging '.$order->order_number)

@section('content')
@php
    $open = in_array($order->status, ['requested', 'scheduled', 'performed'], true);
    $canReport = auth()->user()->can('radiology.report');
@endphp

@include('patients._mini-banner')

<div class="row g-3">
    <div class="col-lg-4 order-lg-2">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $order->order_number }}</span>
                <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
            </div>
            <div class="card-body small">
                <div class="fw-semibold fs-6 mb-2">{{ $order->test->name }} <span class="text-muted fw-normal">· {{ $order->test->modality }}</span></div>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Priority</dt>
                    <dd class="col-7">@if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@else Routine @endif</dd>
                    <dt class="col-5 text-muted fw-normal">Requested by</dt><dd class="col-7">{{ $order->orderedBy?->name }}<br>{{ format_date($order->created_at, true) }}</dd>
                    @if ($order->scheduled_for)
                        <dt class="col-5 text-muted fw-normal">Scheduled</dt><dd class="col-7">{{ format_date($order->scheduled_for, true) }}</dd>
                    @endif
                    @if ($order->performed_at)
                        <dt class="col-5 text-muted fw-normal">Performed</dt><dd class="col-7">{{ format_date($order->performed_at, true) }}<br>{{ $order->performer?->name }}</dd>
                    @endif
                    @if ($order->completed_at)
                        <dt class="col-5 text-muted fw-normal">Reported</dt><dd class="col-7">{{ format_date($order->completed_at, true) }}<br>{{ $order->reporter?->name }}</dd>
                    @endif
                </dl>
                <div class="border-top pt-2 mt-2"><span class="text-muted">Clinical indication:</span> {{ $order->clinical_notes }}</div>
            </div>
            <div class="card-footer bg-white d-grid gap-2">
                @if (in_array($order->status, ['requested', 'scheduled'], true))
                    <form method="POST" action="{{ route('radiology.perform', $order) }}" class="d-grid">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-camera me-1"></i> Mark examination performed</button>
                    </form>
                    <form method="POST" action="{{ route('radiology.schedule', $order) }}" class="row g-1">
                        @csrf
                        <div class="col-6"><input type="date" name="date" class="form-control form-control-sm" required min="{{ today()->toDateString() }}"
                                                  value="{{ $order->scheduled_for?->toDateString() }}" aria-label="Date"></div>
                        <div class="col-3"><input type="time" name="time" class="form-control form-control-sm" required value="{{ $order->scheduled_for?->format('H:i') ?? '09:00' }}" aria-label="Time"></div>
                        <div class="col-3"><button class="btn btn-sm btn-outline-secondary w-100">{{ $order->scheduled_for ? 'Move' : 'Book' }}</button></div>
                    </form>
                @endif
                @if ($order->status === 'completed')
                    <a href="{{ route('radiology.report', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print report</a>
                @endif
            </div>
        </div>

        @if ($previous->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Previous imaging</div>
                <ul class="list-group list-group-flush">
                    @foreach ($previous as $prev)
                        <li class="list-group-item small">
                            <a href="{{ route('radiology.report', $prev) }}" target="_blank" class="fw-semibold text-decoration-none">{{ $prev->test->name }}</a>
                            <span class="text-muted">· {{ format_date($prev->completed_at) }}</span>
                            <div>{{ Str::limit($prev->impression, 120) }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="col-lg-8 order-lg-1">
        @if ($pacsEnabled)
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small">
                        <i class="bi bi-hdd-network me-1"></i> PACS accession number: <strong class="font-monospace">{{ $order->order_number }}</strong>
                        @if ($order->study_instance_uid)
                            <span class="badge text-bg-success ms-1">Images in PACS</span>
                            <span class="d-block text-muted">Linked {{ format_date($order->pacs_linked_at, true) }}</span>
                        @else
                            <span class="d-block text-muted">Enter this number as the Accession Number at the modality; images link automatically.</span>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        @if ($viewerUrl)
                            <a href="{{ $viewerUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i> View images</a>
                        @endif
                        @unless ($order->study_instance_uid)
                            <form method="POST" action="{{ route('radiology.pacs', $order) }}">@csrf
                                <button class="btn btn-sm btn-outline-secondary">Check PACS now</button></form>
                        @endunless
                    </div>
                </div>
            </div>
        @endif
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-images me-1"></i> Images & documents</div>
            <div class="card-body">
                @include('radiology._attachments', ['removable' => $open])
                @if ($open)
                    <form method="POST" action="{{ route('radiology.upload', $order) }}" enctype="multipart/form-data" class="row g-2 mt-2 align-items-end">
                        @csrf
                        <div class="col-md-6">
                            <label for="files" class="form-label small mb-1">Add files (JPEG, PNG, PDF · max 20 MB each)</label>
                            <input type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                                   @class(['form-control form-control-sm', 'is-invalid' => $errors->has('files') || $errors->has('files.*')])>
                            @if ($errors->has('files.*') || $errors->has('files'))<div class="invalid-feedback">{{ $errors->first('files.*') ?: $errors->first('files') }}</div>@endif
                        </div>
                        <div class="col-md-4"><input type="text" name="caption" maxlength="255" class="form-control form-control-sm" placeholder="Caption (e.g. PA view)" aria-label="Caption"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload me-1"></i> Upload</button></div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-medical me-1"></i> Report</div>
            <div class="card-body">
                @if ($order->status === 'performed' && $canReport)
                    <form method="POST" action="{{ route('radiology.report.save', $order) }}">
                        @csrf
                        <x-form.textarea name="technique" label="Technique" :value="$order->technique" rows="2" placeholder="e.g. PA erect view" />
                        <x-form.textarea name="findings" label="Findings" :value="$order->findings" rows="10" required />
                        <x-form.textarea name="impression" label="Impression / conclusion" :value="$order->impression" rows="3" required />
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-primary" name="sign" value="0"><i class="bi bi-save me-1"></i> Save draft</button>
                            <button class="btn btn-success" name="sign" value="1" onclick="return confirm('Sign and release this report to the clinicians?')">
                                <i class="bi bi-patch-check me-1"></i> Sign & release
                            </button>
                        </div>
                    </form>
                @elseif ($order->status === 'performed')
                    <p class="text-muted mb-0"><i class="bi bi-hourglass-split me-1"></i> Waiting for a radiologist to report.</p>
                    @if ($order->findings)<div class="mt-2 small" style="white-space: pre-line;">{{ $order->findings }}</div>@endif
                @elseif ($order->status === 'completed')
                    @include('radiology._report-body')
                @else
                    <p class="text-muted mb-0">The report can be written once the examination has been performed.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
