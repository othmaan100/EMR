@extends('layouts.app')

@section('title', 'Audit Entry #'.$log->id)

@section('content')
<a href="{{ url()->previous() }}" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left me-1"></i> Back</a>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Date / time</dt><dd class="col-sm-9">{{ format_date($log->created_at, true) }}</dd>
            <dt class="col-sm-3">User</dt><dd class="col-sm-9">{{ $log->user?->name ?? 'System' }}</dd>
            <dt class="col-sm-3">Event</dt><dd class="col-sm-9"><span class="badge text-bg-secondary">{{ $log->event }}</span></dd>
            <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $log->description }}</dd>
            @if ($log->auditable_type)
                <dt class="col-sm-3">Record</dt><dd class="col-sm-9">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</dd>
            @endif
            <dt class="col-sm-3">IP address</dt><dd class="col-sm-9">{{ $log->ip_address }}</dd>
            <dt class="col-sm-3">Browser</dt><dd class="col-sm-9 small text-muted">{{ $log->user_agent }}</dd>
            <dt class="col-sm-3">URL</dt><dd class="col-sm-9 small text-muted text-break">{{ $log->url }}</dd>
        </dl>
    </div>
</div>

@if ($log->old_values || $log->new_values)
    @php($fields = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))))
    <div class="card">
        <div class="card-header">Changes</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Field</th><th>Old value</th><th>New value</th></tr></thead>
                <tbody>
                    @foreach ($fields as $field)
                        <tr>
                            <td class="fw-semibold">{{ $field }}</td>
                            <td class="text-danger">{{ is_scalar($log->old_values[$field] ?? null) ? $log->old_values[$field] : json_encode($log->old_values[$field] ?? null) }}</td>
                            <td class="text-success">{{ is_scalar($log->new_values[$field] ?? null) ? $log->new_values[$field] : json_encode($log->new_values[$field] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
