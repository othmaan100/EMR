@extends('portal.layout')

@section('title', 'My health record')

@section('content')
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-calendar-event me-1"></i> Next appointments</span>
                <a href="{{ route('portal.appointments') }}" class="small">All / request</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($appointments as $a)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $a->scheduled_at->format('l') }}, {{ format_date($a->scheduled_at) }}
                            @if ($a->status === 'scheduled') at {{ $a->scheduled_at->format('h:i A') }}@endif</div>
                        <div class="small text-muted">{{ $a->clinic->name }}
                            @if ($a->status === 'requested')<span class="badge text-bg-warning">Waiting for the hospital to confirm</span>@endif</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No upcoming appointments.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Amount you owe</div>
                    <div class="h4 mb-0 {{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</div>
                    @if ($balance > 0)<div class="small text-muted">Pay at the hospital cashier.</div>@endif
                </div>
                <a href="{{ route('portal.bills') }}" class="btn btn-outline-primary btn-sm">Bills &amp; receipts</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-clipboard2-pulse me-1"></i> Latest results</span>
                <a href="{{ route('portal.results') }}" class="small">All results</a></div>
            <ul class="list-group list-group-flush">
                @foreach ($labResults as $o)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><span class="fw-semibold">Laboratory</span> <span class="small text-muted">· {{ format_date($o->completed_at) }}</span></span>
                        <a href="{{ route('portal.results.lab', $o) }}" target="_blank" class="btn btn-sm btn-light">View</a>
                    </li>
                @endforeach
                @foreach ($imagingResults as $o)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><span class="fw-semibold">{{ $o->test->name }}</span> <span class="small text-muted">· {{ format_date($o->completed_at) }}</span></span>
                        <a href="{{ route('portal.results.imaging', $o) }}" target="_blank" class="btn btn-sm btn-light">View</a>
                    </li>
                @endforeach
                @if ($labResults->isEmpty() && $imagingResults->isEmpty())
                    <li class="list-group-item text-muted small">No results yet. Results appear here once the hospital has checked and released them.</li>
                @endif
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-capsule me-1"></i> Recent medicines</div>
            <ul class="list-group list-group-flush">
                @forelse ($medications->flatMap->items as $item)
                    <li class="list-group-item small">
                        <span class="fw-semibold">{{ $item->drug_name }}</span>
                        <span class="d-block text-muted">{{ $item->directions() }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No prescriptions.</li>
                @endforelse
            </ul>
            <div class="card-footer bg-white small text-muted">Always take medicines as your doctor or pharmacist told you.</div>
        </div>
    </div>
</div>
@endsection
