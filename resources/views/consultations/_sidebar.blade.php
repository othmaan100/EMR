<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-heart-pulse me-1"></i> Latest vitals</span>
        @can('vitals.view')<a href="{{ route('vitals.index', $patient) }}" class="small fw-normal">History</a>@endcan
    </div>
    <div class="card-body">
        @if ($latestVitals)
            <div class="small text-muted mb-2">{{ format_date($latestVitals->recorded_at, true) }}{{ $latestVitals->recorded_at->lt(now()->subDay()) ? ' — not from today' : '' }}</div>
            @include('vitals._summary', ['v' => $latestVitals])
        @else
            <span class="text-muted small">No vitals recorded.</span>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Previous consultations</div>
    <ul class="list-group list-group-flush">
        @forelse ($history as $past)
            <li class="list-group-item">
                <a href="{{ route('consultations.show', $past->visit_id) }}" class="small fw-semibold text-decoration-none">
                    {{ format_date($past->signed_at) }} · {{ $past->visit->clinic->name ?? '' }}
                </a>
                <div class="small text-muted">{{ $past->doctor?->name }}</div>
                @foreach ($past->diagnoses as $dx)
                    <div class="small">@if ($dx->is_primary)<i class="bi bi-star-fill text-warning"></i>@endif {{ $dx->description }}</div>
                @endforeach
            </li>
        @empty
            <li class="list-group-item text-muted small">No previous consultations.</li>
        @endforelse
    </ul>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-capsule me-1"></i> Recent prescriptions</div>
    <ul class="list-group list-group-flush">
        @forelse ($recentPrescriptions as $rx)
            <li class="list-group-item small">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">{{ format_date($rx->created_at) }}</span>
                    <span class="badge text-bg-{{ $rx->statusColor() }}">{{ $rx->statusLabel() }}</span>
                </div>
                @foreach ($rx->items as $item)
                    <div>{{ $item->drug_name }} <span class="text-muted">— {{ $item->directions() }}</span></div>
                @endforeach
            </li>
        @empty
            <li class="list-group-item text-muted small">None.</li>
        @endforelse
    </ul>
</div>
