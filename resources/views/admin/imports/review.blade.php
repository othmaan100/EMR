@extends('layouts.app')

@section('title', 'Import review')

@section('content')
@php
    $validated = $import->status === 'validated';
    $completed = $import->status === 'completed';
    $shownErrors = collect($import->errors ?? [])->take(200);
@endphp
<a href="{{ route('admin.imports.show', $import->type) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ $importer->title() }}</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-1 mb-3">
    <div>
        <h1 class="h4 mb-1">{{ $importer->title() }} <span class="badge text-bg-{{ $import->statusColor() }} fs-6 align-middle">{{ $import->statusLabel() }}</span></h1>
        <div class="small text-muted">{{ $import->original_name }} · uploaded {{ format_date($import->created_at, true) }} by {{ $import->user?->name }}
            · {{ $import->mode === 'update' ? 'add new and update existing' : 'add new only' }}
            @if ($import->completed_at) · imported {{ format_date($import->completed_at, true) }}@endif</div>
    </div>
    @if ($import->errors)
        <a href="{{ route('admin.imports.errors', $import) }}" class="btn btn-outline-danger"><i class="bi bi-download me-1"></i> Download rows with errors</a>
    @endif
</div>

@if ($import->status === 'failed')
    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i> {{ $import->message }}</div>
    <a href="{{ route('admin.imports.show', $import->type) }}" class="btn btn-primary">Upload again</a>
@else
    @foreach ($import->warnings ?? [] as $warning)<div class="alert alert-warning small">{{ $warning }}</div>@endforeach

    <div class="row g-3 mb-3">
        @php
            $tiles = $completed
                ? [['Added', $import->created_count, 'success'], ['Updated', $import->updated_count, 'primary'], ['Skipped (already there)', $import->skipped_count, 'secondary'], ['Rows with errors', $import->error_rows, 'danger']]
                : [['Rows in file', $import->total_rows, 'dark'], ['Will be added', $import->create_rows, 'success'], ['Will be updated', $import->update_rows, 'primary'],
                   ['Already exist — will be skipped', $import->skip_rows, 'secondary'], ['Rows with errors', $import->error_rows, 'danger']];
        @endphp
        @foreach ($tiles as [$label, $value, $color])
            <div class="col">
                <div class="card h-100 border-{{ $value > 0 ? $color : 'light' }}">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-4 fw-semibold text-{{ $value > 0 ? $color : 'muted' }}">{{ number_format($value) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($validated)
        <div class="card mb-3 border-primary">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    @if ($import->importableRows() > 0)
                        <strong>Ready.</strong> {{ number_format($import->importableRows()) }} row(s) will be saved.
                        @if ($import->error_rows)<span class="text-danger">{{ number_format($import->error_rows) }} row(s) with errors will be left out</span> — download them, fix and upload again afterwards.@endif
                    @else
                        <strong>Nothing to import.</strong> Fix the errors below and upload the file again.
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.imports.cancel', $import) }}">@csrf
                        <button class="btn btn-outline-secondary">Cancel</button></form>
                    @if ($import->importableRows() > 0)
                        <form method="POST" action="{{ route('admin.imports.run', $import) }}"
                              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Importing…';">
                            @csrf
                            <button class="btn btn-primary px-4"><i class="bi bi-cloud-upload me-1"></i> Import {{ number_format($import->importableRows()) }} row(s)</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($completed)
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> Import complete.
            @if ($import->error_rows) Download the rows with errors, correct them and upload that file to finish.@endif</div>
    @endif

    @if ($shownErrors->isNotEmpty())
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Rows with errors</span>
                @if ($import->error_rows > $shownErrors->count())<span class="small text-muted">Showing the first {{ $shownErrors->count() }} of {{ number_format($import->error_rows) }} — download for all.</span>@endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th class="ps-3">Row</th><th>Record</th><th>Problem</th></tr></thead>
                    <tbody>
                        @foreach ($shownErrors as $error)
                            <tr>
                                <td class="ps-3">{{ $error['row'] }}</td>
                                <td class="text-muted" style="max-width: 20rem;">{{ Str::limit(collect($error['data'])->filter()->take(4)->implode(' · '), 90) }}</td>
                                <td class="text-danger">{{ implode(' ', $error['messages']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
