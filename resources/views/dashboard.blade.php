@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mb-4">
    <h2 class="h4 mb-1">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}</h2>
    <p class="text-muted mb-0">{{ now()->format('l') }}, {{ format_date(now()) }}</p>
    @if (session('previous_login'))
        <p class="small text-muted mb-0 mt-1">
            <i class="bi bi-shield-check me-1"></i>Your previous sign-in:
            {{ format_date(session('previous_login')['at'], true) }} from {{ session('previous_login')['ip'] }}.
            Not you? Change your password and tell the administrator.
        </p>
    @endif
</div>

@if ($healthIssues)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-1"></i> {{ $healthIssues }} system {{ Str::plural('issue', $healthIssues) }} need attention (security, backups or scheduler).</span>
        <a href="{{ route('admin.system.index') }}" class="btn btn-sm btn-warning">System health</a>
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach ($stats as $stat)
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon bg-{{ $stat['color'] }}-subtle text-{{ $stat['color'] }}"><i class="bi {{ $stat['icon'] }}"></i></span>
                    <div>
                        <div class="text-muted small">{{ $stat['label'] }}</div>
                        <div class="h4 mb-0">{{ $stat['value'] }}</div>
                        @isset($stat['note'])<small class="text-muted">{{ $stat['note'] }}</small>@endisset
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if ($worklists)
    <h3 class="h6 text-muted text-uppercase mb-2">Waiting for you</h3>
    <div class="row g-3 mb-4">
        @foreach ($worklists as [$label, $count, $url, $icon, $color])
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a href="{{ $url }}" class="card h-100 text-decoration-none text-body">
                    <div class="card-body d-flex align-items-center gap-3 py-2">
                        <span @class(['stat-icon', "bg-{$color}-subtle text-{$color}" => $count > 0, 'bg-light text-muted' => $count === 0])><i class="bi {{ $icon }}"></i></span>
                        <div>
                            <div class="h5 mb-0">{{ $count }}</div>
                            <div class="small text-muted">{{ $label }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Hospital</div>
            <div class="card-body">
                <div class="d-flex gap-3 align-items-center mb-3">
                    @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="height:56px;width:56px;object-fit:contain">@endif
                    <div>
                        <div class="fw-semibold">{{ setting('hospital_name') }}</div>
                        <small class="text-muted">{{ setting('hospital_type') }}</small>
                    </div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-4 text-muted fw-normal">Address</dt>
                    <dd class="col-8">{{ collect([setting('address'), setting('city'), setting('state'), setting('country')])->filter()->implode(', ') }}</dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8">{{ setting('phone') }}</dd>
                    <dt class="col-4 text-muted fw-normal">Currency</dt><dd class="col-8">{{ setting('currency_code') }} ({{ setting('currency_symbol') }})</dd>
                    <dt class="col-4 text-muted fw-normal">Time zone</dt><dd class="col-8">{{ setting('timezone') }}</dd>
                </dl>
                @can('settings.manage')
                    <a href="{{ route('admin.settings.edit') }}" class="btn btn-sm btn-outline-primary mt-3"><i class="bi bi-pencil me-1"></i> Edit settings</a>
                @endcan
            </div>
        </div>
    </div>

    @can('audit.view')
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Recent activity
                    <a href="{{ route('admin.audit.index') }}" class="small fw-normal">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentActivity as $log)
                        <li class="list-group-item d-flex justify-content-between gap-3 small">
                            <span><strong>{{ $log->user?->name ?? 'System' }}</strong> — {{ $log->description ?? $log->event }}</span>
                            <span class="text-muted text-nowrap">{{ $log->created_at?->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endcan
</div>
@endsection
