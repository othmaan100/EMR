@extends('layouts.app')

@section('title', 'Analyser: '.$analyzer->name)

@section('content')
@php
    $mappings = old('map', $analyzer->mappings->map(fn ($m) => ['analyzer_code' => $m->analyzer_code, 'target' => $m->lab_test_id.($m->lab_test_parameter_id ? ':'.$m->lab_test_parameter_id : '')])->all());
    $mappings[] = ['analyzer_code' => '', 'target' => ''];
@endphp
<a href="{{ route('admin.integrations.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Integrations</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-1 mb-3">
    <h1 class="h4 mb-0">{{ $analyzer->name }} <span class="text-muted fs-6">{{ $analyzer->code }}</span>
        <span class="badge text-bg-{{ $analyzer->is_active ? 'success' : 'secondary' }} fs-6 align-middle">{{ $analyzer->is_active ? 'On' : 'Off' }}</span></h1>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.integrations.analyzers.token', $analyzer) }}" onsubmit="return confirm('Issue a new token? The current one stops working immediately.')">@csrf
            <button class="btn btn-sm btn-outline-secondary">New token</button></form>
        <form method="POST" action="{{ route('admin.integrations.analyzers.toggle', $analyzer) }}">@csrf
            <button class="btn btn-sm btn-outline-{{ $analyzer->is_active ? 'danger' : 'success' }}">{{ $analyzer->is_active ? 'Switch off' : 'Switch on' }}</button></form>
    </div>
</div>

@if (session('analyzer_token'))
    <div class="alert alert-warning">
        <strong>API token (shown only once):</strong>
        <input type="text" readonly class="form-control font-monospace mt-1" value="{{ session('analyzer_token') }}" onclick="this.select()" aria-label="API token">
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-7">
        <form method="POST" action="{{ route('admin.integrations.analyzers.mappings', $analyzer) }}" class="card">
            @csrf
            <div class="card-header">Code mappings</div>
            <div class="card-body">
                <p class="small text-muted">Match each code the analyser sends (HL7 OBX-3 or JSON "code") to a test and, for multi-value tests, the result field. Unmapped codes are ignored and shown in the log.</p>
                @foreach ($errors->get('map.*') as $messages)<div class="text-danger small">{{ implode(' ', $messages) }}</div>@endforeach
                <div id="lines">
                    @foreach ($mappings as $i => $m)
                        <div class="row g-2 mb-2" data-line>
                            <div class="col-4"><input type="text" name="map[{{ $i }}][analyzer_code]" value="{{ $m['analyzer_code'] }}" maxlength="50" class="form-control form-control-sm font-monospace" placeholder="e.g. WBC" aria-label="Analyser code"></div>
                            <div class="col-7">
                                <select name="map[{{ $i }}][target]" class="form-select form-select-sm" aria-label="Test / result field">
                                    <option value="">Test / result field…</option>
                                    @foreach ($tests as $test)
                                        <optgroup label="{{ $test->name }}">
                                            @if ($test->parameters->isEmpty())
                                                <option value="{{ $test->id }}" @selected($m['target'] === (string) $test->id)>{{ $test->name }} (single result)</option>
                                            @endif
                                            @foreach ($test->parameters as $p)
                                                <option value="{{ $test->id }}:{{ $p->id }}" @selected($m['target'] === $test->id.':'.$p->id)>{{ $test->name }} — {{ $p->name }}{{ $p->unit ? " ({$p->unit})" : '' }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-1"><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add mapping</button>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save mappings</button></div>
        </form>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">How to connect</div>
            <div class="card-body small">
                <ol class="mb-2 ps-3">
                    <li>Print sample labels from the lab as usual — the barcode is the <strong>lab order number</strong> (e.g. LAB2026-000123). The analyser reads it as the sample ID.</li>
                    <li>In the analyser's LIS / middleware settings, send results (HL7 v2 ORU^R01) by HTTP POST to:<br><code>{{ url('api/v1/lab/results') }}</code></li>
                    <li>Add the header <code>Authorization: Bearer &lt;token&gt;</code> (token hint …{{ $analyzer->token_hint }}).</li>
                    <li>Results appear as <em>entered by {{ $analyzer->user->name }}</em>; a scientist reviews and <strong>verifies</strong> them as usual.</li>
                </ol>
                <p class="mb-1">Instruments that only speak ASTM or serial (RS-232) need a small middleware or serial-to-network bridge that forwards HL7 or JSON. JSON example:</p>
                <pre class="bg-light border rounded p-2 mb-0">{"sample_id": "LAB2026-000123",
 "results": [{"code": "WBC", "value": "6.1"}]}</pre>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Recent messages</div>
            <ul class="list-group list-group-flush small">
                @forelse ($recent as $m)
                    <li class="list-group-item"><span class="badge text-bg-{{ ['ok' => 'success', 'partial' => 'warning', 'error' => 'danger'][$m->status] ?? 'secondary' }}">{{ $m->status }}</span>
                        {{ format_date($m->created_at, true) }} — {{ Str::after($m->summary, ': ') }}</li>
                @empty
                    <li class="list-group-item text-muted">Nothing received yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@include('stores._lines-js')
@endsection
