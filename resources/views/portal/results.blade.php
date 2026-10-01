@extends('portal.layout')

@section('title', 'Results')

@section('content')
<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i> Only results checked and released by the hospital appear here.
    Please discuss them with your doctor. A result outside the normal range is not always a problem.
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-droplet-half me-1"></i> Laboratory</div>
    <ul class="list-group list-group-flush">
        @forelse ($labOrders as $o)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">{{ $o->items->pluck('test.name')->filter()->implode(', ') }}</div>
                    <div class="small text-muted">{{ format_date($o->completed_at) }} · {{ $o->order_number }}
                        @if ($o->abnormalCount())<span class="badge text-bg-warning">{{ $o->abnormalCount() }} outside normal range</span>@endif</div>
                </div>
                <a href="{{ route('portal.results.lab', $o) }}" target="_blank" class="btn btn-sm btn-outline-primary">View / print</a>
            </li>
        @empty
            <li class="list-group-item text-muted small">No laboratory results.</li>
        @endforelse
    </ul>
    @if ($labOrders->hasPages())<div class="card-footer bg-white">{{ $labOrders->links() }}</div>@endif
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-radioactive me-1"></i> Imaging (X-ray, ultrasound, scans)</div>
    <ul class="list-group list-group-flush">
        @forelse ($imagingOrders as $o)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">{{ $o->test->name }}</div>
                    <div class="small text-muted">{{ format_date($o->completed_at) }} · {{ $o->order_number }}</div>
                </div>
                <a href="{{ route('portal.results.imaging', $o) }}" target="_blank" class="btn btn-sm btn-outline-primary">View / print</a>
            </li>
        @empty
            <li class="list-group-item text-muted small">No imaging reports.</li>
        @endforelse
    </ul>
</div>
@endsection
