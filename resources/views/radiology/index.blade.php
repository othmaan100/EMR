@extends('layouts.app')

@section('title', 'Radiology')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach (\App\Http\Controllers\RadiologyController::TABS as $key => $t)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $tab === $key]) href="{{ route('radiology.index', ['tab' => $key]) }}">
                    <i class="bi {{ $t['icon'] }} me-1"></i>{{ $t['label'] }}
                    <span class="badge text-bg-light ms-1">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Order no. or patient…" aria-label="Search" style="min-width: 240px;">
        <button class="btn btn-outline-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Order</th><th>Patient</th><th>Examination</th><th>Clinical indication</th><th>When</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr data-href="{{ route('radiology.show', $order) }}">
                        <td class="ps-3">
                            <span class="fw-semibold">{{ $order->order_number }}</span>
                            @if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@endif
                        </td>
                        <td>
                            {{ $order->patient->list_name }}
                            <small class="d-block text-muted">{{ $order->patient->hospital_number }} · {{ ucfirst($order->patient->gender) }} · {{ $order->patient->age ?? '?' }}</small>
                        </td>
                        <td>{{ $order->test->name }} <small class="d-block text-muted">{{ $order->test->modality }}</small></td>
                        <td class="small">{{ Str::limit($order->clinical_notes, 60) }}</td>
                        <td class="small text-nowrap">
                            @if ($order->status === 'scheduled')
                                <i class="bi bi-calendar-event"></i> {{ format_date($order->scheduled_for, true) }}
                            @elseif ($order->status === 'completed')
                                {{ $order->completed_at->format('h:i A') }}
                            @else
                                {{ $order->created_at->diffForHumans() }}
                                <span class="d-block text-muted">{{ $order->orderedBy?->name }}</span>
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
                        <td class="text-end pe-3 text-nowrap">
                            @if ($order->status === 'completed')
                                <a href="{{ route('radiology.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Report</a>
                            @else
                                <a href="{{ route('radiology.show', $order) }}" class="btn btn-sm btn-primary">Open</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
</div>
@endsection
