@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="card mb-3">
    <div class="card-body">@include('reports._range')</div>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['New patients', $overview['new_patients'], 'int', 'bi-person-plus', 'info'],
        ['Clinic visits', $overview['visits'], 'int', 'bi-people-fill', 'primary'],
        ['Admissions', $overview['admissions'], 'int', 'bi-hospital', 'warning'],
        ['Money collected', $overview['collected'], 'money', 'bi-cash-coin', 'success'],
    ] as [$label, $value, $format, $icon, $color])
        @if ($format !== 'money' || auth()->user()->can('reports.financial'))
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="h4 mb-0">@include('reports._value', ['value' => $value, 'format' => $format])</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

@foreach ($groups as $group => $reports)
    <h2 class="h6 text-muted text-uppercase mb-2">{{ $group }}</h2>
    <div class="row g-3 mb-4">
        @foreach ($reports as $key => [$title, , , $description])
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('reports.show', ['report' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                   class="card h-100 text-decoration-none text-body">
                    <div class="card-body">
                        <div class="fw-semibold text-brand mb-1"><i class="bi bi-bar-chart-line me-1"></i>{{ $title }}</div>
                        <div class="small text-muted">{{ $description }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
