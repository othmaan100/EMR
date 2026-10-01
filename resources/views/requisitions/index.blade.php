@extends('layouts.app')

@section('title', 'Requisitions')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        @if ($canStore)
            <li class="nav-item"><a href="{{ route('requisitions.index', ['tab' => 'open']) }}" @class(['nav-link', 'active' => $tab === 'open'])>To issue</a></li>
        @endif
        <li class="nav-item"><a href="{{ route('requisitions.index', ['tab' => 'mine']) }}" @class(['nav-link', 'active' => $tab === 'mine'])>Mine / my department</a></li>
        @if ($canStore)
            <li class="nav-item"><a href="{{ route('requisitions.index', ['tab' => 'all']) }}" @class(['nav-link', 'active' => $tab === 'all'])>All</a></li>
        @endif
    </ul>
    @can('requisitions.create')
        <a href="{{ route('requisitions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New requisition</a>
    @endcan
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Number</th><th>Department</th><th>Requested</th><th>Needed by</th><th class="text-center">Items</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($requisitions as $r)
                    <tr>
                        <td class="ps-3"><a href="{{ route('requisitions.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->requisition_number }}</a></td>
                        <td>{{ $r->department->name }}</td>
                        <td class="small">{{ format_date($r->created_at, true) }}<span class="d-block text-muted">{{ $r->requester?->name }}</span></td>
                        <td class="small">
                            {{ $r->needed_by ? format_date($r->needed_by) : '—' }}
                            @if ($r->isOpen() && $r->needed_by?->lt(today()))<span class="badge text-bg-danger">Late</span>@endif
                        </td>
                        <td class="text-center">{{ $r->items->count() }}</td>
                        <td><span class="badge text-bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No requisitions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requisitions->hasPages())<div class="card-footer bg-white">{{ $requisitions->links() }}</div>@endif
</div>
@endsection
