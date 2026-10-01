@extends('layouts.app')

@section('title', 'Nursing / Triage')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split me-1"></i> Waiting for triage</span>
                <span class="badge text-bg-warning">{{ $waiting->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($waiting as $visit)
                    @php($wait = $visit->minutesInStage())
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <span class="badge bg-brand fs-6">{{ $visit->queue_number }}</span>
                        <div class="flex-grow-1">
                            <a href="{{ route('patients.show', $visit->patient) }}" class="fw-semibold text-decoration-none">{{ $visit->patient->list_name }}</a>
                            @if ($visit->priority !== 'normal')
                                <span class="badge text-bg-{{ \App\Models\Visit::PRIORITIES[$visit->priority]['color'] }}">{{ \App\Models\Visit::PRIORITIES[$visit->priority]['label'] }}</span>
                            @endif
                            <div class="small text-muted">
                                {{ ucfirst($visit->patient->gender) }} · {{ $visit->patient->age ?? '?' }} · {{ $visit->clinic->name }}
                                @if ($visit->complaint) · {{ Str::limit($visit->complaint, 50) }}@endif
                            </div>
                        </div>
                        <small @class(['text-nowrap', 'text-danger fw-semibold' => $wait >= 60, 'text-warning' => $wait >= 30 && $wait < 60, 'text-muted' => $wait < 30])>
                            <i class="bi bi-clock"></i> {{ $wait }}m
                        </small>
                        <a href="{{ route('vitals.triage', $visit) }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-heart-pulse me-1"></i> Take vitals</a>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-5">
                        <i class="bi bi-cup-hot fs-2 d-block mb-2"></i>No one is waiting for triage.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Recorded in the last 12 hours</div>
            <ul class="list-group list-group-flush">
                @forelse ($recent as $v)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('vitals.index', $v->patient) }}" class="fw-semibold text-decoration-none small">{{ $v->patient->list_name }}</a>
                            <small class="text-muted">{{ $v->recorded_at->format('h:i A') }} · {{ $v->recorder?->name }}</small>
                        </div>
                        @include('vitals._summary', ['v' => $v, 'compact' => true])
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Nothing recorded yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
