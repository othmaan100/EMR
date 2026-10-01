@extends('layouts.app')

@section('title', 'Integrations')

@section('content')
@php
    $cards = [
        'lab' => ['Lab analysers', 'bi-cpu', 'HL7 v2 or JSON results posted by analysers / middleware.'],
        'pacs' => ['PACS / DICOM images', 'bi-radioactive', 'Imaging studies linked by accession number, with a viewer link.'],
        'payment' => ['Online payments', 'bi-credit-card', 'Paystack or Flutterwave: portal payments and payment links.'],
        'claims' => ['NHIA / HMO e-claims', 'bi-send-check', 'Electronic claim file, and submission to a claims API.'],
        'nin' => ['National ID (NIN)', 'bi-person-vcard', 'Look up a NIN at registration through a licensed partner.'],
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Connections to outside systems. Credentials are entered in
        @can('settings.manage')<a href="{{ route('admin.settings.edit', ['section' => 'integrations']) }}">Hospital Settings → Integrations</a>@else Hospital Settings → Integrations @endcan.</p>
    @if ($errorsToday)<span class="badge text-bg-danger fs-6">{{ $errorsToday }} error(s) today</span>@endif
</div>

<div class="row g-3 mb-4">
    @foreach ($cards as $key => [$title, $icon, $text])
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-semibold"><i class="bi {{ $icon }} me-1"></i>{{ $title }}</span>
                        <span class="badge text-bg-{{ $status[$key] ? 'success' : 'light border' }}">{{ $status[$key] ? 'On' : 'Not set up' }}</span>
                    </div>
                    <div class="small text-muted mb-2">{{ $text }}</div>
                    @if ($key === 'pacs' && $status['pacs'])
                        <form method="POST" action="{{ route('admin.integrations.test-pacs') }}">@csrf
                            <button class="btn btn-sm btn-outline-secondary">Test connection</button></form>
                    @endif
                    <a href="{{ route('admin.integrations.index', ['channel' => $key]) }}" class="small">Messages</a>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Lab analysers</div>
            <ul class="list-group list-group-flush">
                @forelse ($analyzers as $a)
                    <a href="{{ route('admin.integrations.analyzer', $a) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">{{ $a->name }} <span class="small text-muted">{{ $a->code }}</span></span>
                            <span class="badge text-bg-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'On' : 'Off' }}</span>
                        </div>
                        <div class="small text-muted">{{ $a->mappings_count }} code(s) mapped · last message {{ $a->last_message_at?->diffForHumans() ?? 'never' }}</div>
                    </a>
                @empty
                    <li class="list-group-item small text-muted">No analysers yet.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('admin.integrations.analyzers.store') }}" class="card-body border-top">
                @csrf
                <div class="row g-2">
                    <x-form.input name="name" label="Analyser name" required col="col-7" placeholder="e.g. Sysmex XN-350" />
                    <x-form.input name="code" label="Code" required col="col-5" maxlength="20" placeholder="SYSMEX1" />
                </div>
                <button class="btn btn-sm btn-primary mt-2">Add analyser</button>
                <div class="form-text">Results are sent to <code>{{ url('api/v1/lab/results') }}</code> with the analyser's token.</div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Message log {{ $channel ? '— '.\App\Models\IntegrationMessage::CHANNELS[$channel] : '' }}</span>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('admin.integrations.index') }}" @class(['badge text-decoration-none', 'text-bg-dark' => ! $channel, 'text-bg-light border' => $channel])>All</a>
                    @foreach (\App\Models\IntegrationMessage::CHANNELS as $key => $label)
                        <a href="{{ route('admin.integrations.index', ['channel' => $key]) }}" @class(['badge text-decoration-none', 'text-bg-dark' => $channel === $key, 'text-bg-light border' => $channel !== $key])>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th class="ps-3">When</th><th>Channel</th><th></th><th>What happened</th><th>Reference</th></tr></thead>
                    <tbody>
                        @forelse ($messages as $m)
                            <tr>
                                <td class="ps-3 text-nowrap">{{ format_date($m->created_at, true) }}</td>
                                <td>{{ \App\Models\IntegrationMessage::CHANNELS[$m->channel] ?? $m->channel }} <span class="text-muted">{{ $m->direction === 'in' ? '←' : '→' }}</span></td>
                                <td><span class="badge text-bg-{{ ['ok' => 'success', 'partial' => 'warning', 'error' => 'danger'][$m->status] ?? 'secondary' }}">{{ $m->status }}</span></td>
                                <td>{{ $m->summary }} @if ($m->payload)<a href="{{ route('admin.integrations.message', $m) }}" class="ms-1">raw</a>@endif</td>
                                <td class="text-muted">{{ $m->reference }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No messages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($messages->hasPages())<div class="card-footer bg-white">{{ $messages->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
