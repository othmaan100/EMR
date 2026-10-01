@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <x-form.select name="user_id" label="User" :options="$users->pluck('name', 'id')->all()" :value="$filters['user_id'] ?? ''" placeholder="All users" col="col-md-3" />
            <x-form.select name="event" label="Event" :options="$events->combine($events)->all()" :value="$filters['event'] ?? ''" placeholder="All events" col="col-md-2" />
            <x-form.input name="from" type="date" label="From" :value="$filters['from'] ?? ''" col="col-md-2" />
            <x-form.input name="to" type="date" label="To" :value="$filters['to'] ?? ''" col="col-md-2" />
            <x-form.input name="q" label="Search" :value="$filters['q'] ?? ''" col="col-md-3" placeholder="Description..." />
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date / time</th>
                    <th>User</th>
                    <th>Event</th>
                    <th>Description</th>
                    <th>IP address</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ format_date($log->created_at, true) }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><span class="badge text-bg-{{ match ($log->event) { 'created' => 'success', 'deleted' => 'danger', 'login_failed' => 'warning', 'updated', 'settings_updated' => 'info', default => 'secondary' } }}">{{ $log->event }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="small text-muted">{{ $log->ip_address }}</td>
                        <td class="text-end"><a href="{{ route('admin.audit.show', $log) }}" class="btn btn-sm btn-light" title="Details"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No log entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="card-footer bg-white">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
