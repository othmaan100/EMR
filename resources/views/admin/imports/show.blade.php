@extends('layouts.app')

@section('title', 'Import: '.$importer->title())

@section('content')
<a href="{{ route('admin.imports.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Data Import</a>
<h1 class="h4 mt-1">Step {{ $step }}: {{ $importer->title() }}</h1>
<p class="text-muted">{{ $importer->description() }}</p>

@if ($dependencies->isNotEmpty())
    <div class="alert alert-light border small">
        <i class="bi bi-diagram-3 me-1"></i> Import these first (if you have them):
        @foreach ($dependencies as $dep)
            <a href="{{ route('admin.imports.show', $dep->key()) }}">{{ $dep->title() }}</a>@if (! $loop->last), @endif
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-5 order-xl-2">
        <div class="card mb-3">
            <div class="card-header"><span class="badge rounded-pill bg-primary me-1">1</span> Download the template</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <a href="{{ route('admin.imports.template', [$importer->key(), 'format' => 'xlsx']) }}" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel template (.xlsx)</a>
                    <a href="{{ route('admin.imports.template', [$importer->key(), 'format' => 'csv']) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-filetype-csv me-1"></i> CSV template</a>
                </div>
                <a href="{{ route('admin.imports.template', [$importer->key(), 'format' => 'xlsx', 'sample' => 1]) }}" class="small">
                    <i class="bi bi-eye me-1"></i> Sample file with example rows</a>
                <p class="small text-muted mt-2 mb-0">The Excel template has an <strong>Instructions</strong> sheet explaining every column. Keep the column headings; red headings are required.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="badge rounded-pill bg-primary me-1">2</span> Upload the filled file</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.imports.upload', $importer->key()) }}" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label">File (.xlsx or .csv, up to 20 MB) <span class="text-danger">*</span></label>
                        <input type="file" id="file" name="file" required accept=".xlsx,.csv,text/csv" @class(['form-control', 'is-invalid' => $errors->has('file')])>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($importer->supportsUpdate())
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Records that already exist</legend>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="create" id="mode_create" @checked(old('mode', 'create') === 'create')>
                                <label class="form-check-label" for="mode_create">Skip them — only add new records</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="update" id="mode_update" @checked(old('mode') === 'update')>
                                <label class="form-check-label" for="mode_update">Update them with the file's values <span class="text-muted small">(blank cells keep the current value)</span></label>
                            </div>
                            <div class="form-text">{{ $importer->matchDescription() }}</div>
                        </fieldset>
                    @else
                        <input type="hidden" name="mode" value="create">
                        <p class="small text-muted">{{ $importer->matchDescription() }}</p>
                    @endif
                    @foreach ($importer->options() as $name => [$label, $type, $rules, $help])
                        <div class="mb-3">
                            <label for="opt_{{ $name }}" class="form-label">{{ $label }}</label>
                            <input type="{{ $type }}" id="opt_{{ $name }}" name="options[{{ $name }}]" @if ($type === 'password') autocomplete="new-password" @else value="{{ old("options.$name") }}" @endif
                                   @class(['form-control', 'is-invalid' => $errors->has("options.$name")])>
                            @if ($help)<div class="form-text">{{ $help }}</div>@endif
                            @error("options.$name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Check file</button>
                    <div class="form-text text-center">Nothing is saved yet — you will see a preview first.</div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7 order-xl-1">
        @if ($importer->notes())
            <div class="card mb-3">
                <div class="card-header">Before you start</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($importer->notes() as $note)<li class="list-group-item">{{ $note }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div class="card mb-3">
            <div class="card-header">Columns</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th class="ps-3">Column</th><th>What to enter</th><th>Example</th></tr></thead>
                    <tbody>
                        @foreach ($importer->columns() as $col)
                            <tr>
                                <td class="ps-3 text-nowrap"><code>{{ $col->name }}</code>@if ($col->required)<span class="badge text-bg-danger ms-1">required</span>@endif</td>
                                <td>
                                    {{ $col->help ?? $col->label }}
                                    @if ($col->allowed)<div class="text-muted">Allowed: {{ implode(', ', $col->allowed) }}</div>@endif
                                </td>
                                <td class="text-muted">{{ $col->example }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($previous->isNotEmpty())
            <div class="card">
                <div class="card-header">Previous imports of this type</div>
                @include('admin.imports._table', ['imports' => $previous])
            </div>
        @endif
    </div>
</div>
@endsection
