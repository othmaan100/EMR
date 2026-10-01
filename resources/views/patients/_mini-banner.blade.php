<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        @if ($patient->photo)
            <img src="{{ route('patients.photo', $patient) }}" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover">
        @else
            <span class="avatar bg-secondary" style="width:56px;height:56px">{{ $patient->initials }}</span>
        @endif
        <div class="flex-grow-1">
            <a href="{{ route('patients.show', $patient) }}" class="fw-semibold text-decoration-none">{{ $patient->full_name }}</a>
            <div class="small text-muted">{{ $patient->hospital_number }} · {{ ucfirst($patient->gender) }} · {{ $patient->age ?? 'Age unknown' }} · {{ $patient->paymentLabel() }}</div>
            @if ($patient->allergies)
                <span class="badge text-bg-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Allergies: {{ Str::limit($patient->allergies, 80) }}</span>
            @endif
        </div>
        @isset($visit)
            @if ($visit)
                <div class="text-end small">
                    <span class="badge bg-brand fs-6">{{ $visit->queue_number }}</span>
                    <div class="text-muted">{{ $visit->clinic->name ?? '' }}</div>
                    @if ($visit->complaint)<div><i class="bi bi-chat-left-text me-1"></i>{{ $visit->complaint }}</div>@endif
                </div>
            @endif
        @endisset
    </div>
</div>
