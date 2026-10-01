@extends('layouts.app')

@section('title', 'Laboratory')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach (\App\Http\Controllers\LaboratoryController::TABS as $key => $t)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $tab === $key]) href="{{ route('lab.index', ['tab' => $key]) }}">
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
                <tr><th class="ps-3">Order</th><th>Patient</th><th>Tests</th><th>Requested</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr data-href="{{ route('lab.show', $order) }}">
                        <td class="ps-3">
                            <span class="fw-semibold">{{ $order->order_number }}</span>
                            @if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@endif
                        </td>
                        <td>
                            {{ $order->patient->list_name }}
                            <small class="d-block text-muted">{{ $order->patient->hospital_number }} · {{ ucfirst($order->patient->gender) }} · {{ $order->patient->age ?? '?' }}</small>
                        </td>
                        <td class="small">{{ $order->items->where('status', '!=', 'cancelled')->map(fn ($i) => $i->test->name)->implode(', ') }}</td>
                        <td class="small text-nowrap">
                            {{ $order->created_at->diffForHumans() }}
                            <span class="d-block text-muted">{{ $order->orderedBy?->name }}</span>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                            @if ($order->rejection_reason && $order->status === 'requested')
                                <span class="badge text-bg-danger" title="{{ $order->rejection_reason }}">Recollect</span>
                            @endif
                        </td>
                        <td class="text-end pe-3 text-nowrap">
                            @switch($order->status)
                                @case('requested')
                                    <form method="POST" action="{{ route('lab.collect', $order) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-primary"><i class="bi bi-droplet me-1"></i>Collect</button>
                                    </form>
                                    @break
                                @case('collected')
                                    <a href="{{ route('lab.show', $order) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Enter results</a>
                                    @break
                                @case('in_progress')
                                    <a href="{{ route('lab.show', $order) }}" class="btn btn-sm btn-warning"><i class="bi bi-patch-question me-1"></i>Review</a>
                                    @break
                                @case('completed')
                                    <a href="{{ route('lab.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Report</a>
                                    @break
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
</div>
@endsection
