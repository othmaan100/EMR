@extends('layouts.app')

@section('title', 'Data Import')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div style="max-width: 48rem;">
        <p class="mb-1">Bring records over from your previous system, paper registers or spreadsheets.</p>
        <p class="small text-muted mb-0">
            For each type: download the template, fill one record per row, upload it. Every row is checked first and <strong>nothing is saved
            until you confirm</strong>. Rows with problems can be downloaded, fixed and uploaded again. Work through the steps in order: later
            imports refer to earlier ones by code.
        </p>
    </div>
    <a href="{{ route('admin.imports.history') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i> Import history</a>
</div>

@foreach ($groups as $group => $importers)
    <h2 class="h6 text-muted text-uppercase mt-4 mb-2">{{ $group }}</h2>
    <div class="row g-3">
        @foreach ($importers as $importer)
            @php
                $stats = $done[$importer->key()] ?? null;
                $allowed = ! $importer->permission() || auth()->user()->can($importer->permission());
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h3 class="h6 mb-1">
                                <span class="badge rounded-pill text-bg-light border me-1">{{ \App\Imports\ImportRegistry::step($importer->key()) }}</span>
                                {{ $importer->title() }}
                            </h3>
                            @if ($stats)<span class="badge text-bg-success" title="Last imported {{ format_date($stats->last, true) }}"><i class="bi bi-check2"></i> {{ number_format($stats->created) }} added</span>@endif
                        </div>
                        <p class="small text-muted flex-grow-1 mb-2">{{ $importer->description() }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($allowed)
                                <a href="{{ route('admin.imports.show', $importer->key()) }}" class="btn btn-sm btn-primary"><i class="bi bi-cloud-upload me-1"></i> Import</a>
                            @else
                                <span class="small text-muted">Needs the “{{ $importer->permission() }}” permission.</span>
                            @endif
                            <a href="{{ route('admin.imports.template', [$importer->key(), 'format' => 'xlsx']) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-excel me-1"></i> Template</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach

@if ($recent->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header">Recent imports</div>
        @include('admin.imports._table', ['imports' => $recent])
    </div>
@endif
@endsection
