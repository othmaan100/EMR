@extends('layouts.app')

@section('title', $def[0])

@section('content')
@php
    $chart = $result['chart'] ?? null;
    $columns = $result['columns'];
    $rows = $result['rows'];
    $numeric = fn ($format) => in_array($format, ['int', 'money', 'dec1', 'pct'], true);
    $chartHeight = $chart && ($chart['horizontal'] ?? false) ? max(200, count($chart['labels']) * 26 + 60) : 260;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <a href="{{ route('reports.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i> All reports</a>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv me-1"></i> Export CSV (Excel)</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        @component('reports._range', ['from' => $from, 'to' => $to, 'extra' => []])
            @if ($clinics->isNotEmpty())
                <div>
                    <label for="clinic_id" class="form-label small mb-1">Clinic</label>
                    <select id="clinic_id" name="clinic_id" class="form-select form-select-sm">
                        <option value="">All clinics</option>
                        @foreach ($clinics as $id => $name)<option value="{{ $id }}" @selected(($filters['clinic_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if ($wards->isNotEmpty())
                <div>
                    <label for="ward_id" class="form-label small mb-1">Ward</label>
                    <select id="ward_id" name="ward_id" class="form-select form-select-sm">
                        <option value="">All wards</option>
                        @foreach ($wards as $id => $name)<option value="{{ $id }}" @selected(($filters['ward_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif
        @endcomponent
    </div>
</div>

{{-- Print header --}}
<div class="d-none d-print-block mb-3">
    <div class="h5 mb-0">{{ setting('hospital_name') }}</div>
    <div>{{ $def[0] }} · {{ format_date($from) }} – {{ format_date($to) }}</div>
</div>

<p class="text-muted small">{{ $def[3] }} @if (! empty($result['note']))<strong>{{ $result['note'] }}</strong>@endif</p>

@if (! empty($result['tiles']))
    <div class="row g-3 mb-3">
        @foreach ($result['tiles'] as [$label, $value, $format])
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="h5 mb-0">@include('reports._value', ['value' => $value, 'format' => $format])</div>
                </div></div>
            </div>
        @endforeach
    </div>
@endif

@if ($chart && count($rows))
    <div class="card mb-3">
        <div class="card-header small">{{ $chart['title'] }}</div>
        <div class="card-body" style="height: {{ $chartHeight }}px;">
            <canvas data-trend-chart="{{ json_encode($chart) }}" role="img" aria-label="{{ $chart['title'] }}. The same figures are in the table below."></canvas>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>{{ number_format(count($rows)) }} {{ Str::plural('row', count($rows)) }}</span>
        <span class="small text-muted fw-normal">{{ format_date($from) }} – {{ format_date($to) }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @foreach ($columns as [$label, $format])
                        <th @class(['text-end' => $numeric($format), 'ps-3' => $loop->first])>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $key => [$label, $format])
                            <td @class(['text-end' => $numeric($format), 'ps-3' => $loop->first])>@include('reports._value', ['value' => $row[$key] ?? null, 'format' => $format])</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">No data for this period.</td></tr>
                @endforelse
            </tbody>
            @if (count($rows) > 1)
                <tfoot class="table-light fw-semibold">
                    <tr>
                        @foreach ($columns as $key => $col)
                            @php
                                [$label, $format] = $col;
                            @endphp
                            <td @class(['text-end' => $numeric($format), 'ps-3' => $loop->first])>
                                @if ($loop->first)
                                    Total
                                @elseif (in_array($format, ['int', 'money'], true) && ($col[2] ?? true))
                                    @include('reports._value', ['value' => array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows)), 'format' => $format])
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
