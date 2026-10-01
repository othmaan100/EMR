@extends('layouts.app')

@section('title', 'System Health & Backups')

@section('content')
@php
    $icon = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-circle-fill text-warning', 'fail' => 'bi-x-circle-fill text-danger'];
    $word = ['ok' => 'OK', 'warn' => 'Check', 'fail' => 'Action needed'];
@endphp

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Health checks</div>
            <ul class="list-group list-group-flush">
                @foreach ($checks as $check)
                    <li class="list-group-item d-flex gap-3 align-items-start">
                        <i class="bi {{ $icon[$check['status']] }} fs-5" aria-hidden="true"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $check['label'] }}</strong>
                                <span class="small text-muted">{{ $word[$check['status']] }}</span>
                            </div>
                            <div class="small">{{ $check['detail'] }}</div>
                            @if (! empty($check['fix']) && $check['status'] !== 'ok')
                                <div class="small text-muted"><i class="bi bi-wrench me-1"></i>{{ $check['fix'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Backups</span>
                <form method="POST" action="{{ route('admin.system.backup') }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button class="btn btn-sm btn-primary"><i class="bi bi-cloud-arrow-down me-1"></i> Back up now</button>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($backups as $b)
                    <li class="list-group-item d-flex justify-content-between align-items-center small">
                        <span>
                            <span class="fw-semibold">{{ format_date($b['created'], true) }}</span>
                            <span class="text-muted d-block">{{ $b['name'] }} · {{ app(\App\Services\BackupService::class)->human($b['size']) }}</span>
                        </span>
                        @if (auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.system.download', $b['name']) }}" class="btn btn-sm btn-light" title="Download"><i class="bi bi-download"></i></a>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No backups yet.</li>
                @endforelse
            </ul>
            <div class="card-footer bg-white small text-muted">
                Stored in <code>{{ $directory }}</code>. Nightly at 01:30; kept {{ setting('backup_retention_days') }} days.
                <strong>Copy backups to another device or cloud storage regularly</strong> — a backup on the same computer won't survive a disk failure or theft.
                @unless (auth()->user()->isSuperAdmin())<br>Only a Super Admin can download backups.@endunless
            </div>
        </div>

        <div class="card">
            <div class="card-header">Restoring a backup</div>
            <div class="card-body small">
                <ol class="mb-0 ps-3">
                    <li>Unzip the backup file.</li>
                    <li>Import <code>database.sql</code> into an empty database: <code>mysql -u root emr &lt; database.sql</code></li>
                    <li>Copy <code>files/private</code> back to <code>storage/app/private</code> and <code>files/uploads</code> to <code>public/uploads</code>.</li>
                    <li>Run <code>php artisan migrate --force</code> and <code>php artisan optimize:clear</code>.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
