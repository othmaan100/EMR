<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
    <style>
        body { background: #f4f6f9; }
        .portal-nav .nav-link { color: #495057; border-radius: .5rem; }
        .portal-nav .nav-link.active { background: var(--brand); color: #fff; }
    </style>
</head>
<body>
@php
    $links = [
        'portal.dashboard' => ['Home', 'bi-house'],
        'portal.appointments' => ['Appointments', 'bi-calendar-event'],
        'portal.results' => ['Results', 'bi-clipboard2-pulse'],
        'portal.bills' => ['Bills', 'bi-receipt'],
        'portal.immunizations' => ['Immunizations', 'bi-shield-plus'],
        'portal.profile' => ['My details', 'bi-person'],
    ];
@endphp
<nav class="navbar bg-white border-bottom sticky-top">
    <div class="container" style="max-width: 960px;">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('portal.dashboard') }}">
            @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="height:32px;width:32px;object-fit:contain">@endif
            <span class="fs-6 fw-semibold">{{ setting('hospital_short_name') ?: setting('hospital_name') }}</span>
        </a>
        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i> Sign out</button>
        </form>
    </div>
</nav>

<main class="container py-3" style="max-width: 960px;">
    @if ($family->count() > 1)
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="small text-muted">Viewing records for:</span>
            @foreach ($family as $member)
                <form method="POST" action="{{ route('portal.switch', $member) }}">
                    @csrf
                    <button @class(['btn btn-sm', 'btn-primary' => $member->id === $patient->id, 'btn-outline-secondary' => $member->id !== $patient->id])>
                        {{ $member->first_name }}{{ $member->id === $account->patient_id ? ' (me)' : '' }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <div class="d-flex align-items-center gap-2 mb-3">
        <div>
            <div class="h5 mb-0">{{ $patient->full_name }}</div>
            <div class="small text-muted">{{ $patient->hospital_number }} @if ($patient->age)· {{ $patient->age }}@endif</div>
        </div>
    </div>

    <ul class="nav nav-pills portal-nav flex-nowrap overflow-auto mb-3 pb-1">
        @foreach ($links as $route => [$label, $icon])
            <li class="nav-item"><a href="{{ route($route) }}" @class(['nav-link text-nowrap', 'active' => request()->routeIs($route)])><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a></li>
        @endforeach
    </ul>

    @include('layouts.partials.flash')
    @yield('content')

    <p class="text-center small text-muted mt-4">
        {{ setting('hospital_name') }} · {{ setting('phone') }}<br>
        For emergencies, come to the hospital or call {{ setting('phone') }}. Messages sent here are not monitored.
    </p>
</main>
</body>
</html>
