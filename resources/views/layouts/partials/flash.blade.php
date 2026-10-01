@foreach (['success' => 'check-circle', 'error' => 'exclamation-triangle', 'warning' => 'exclamation-circle', 'info' => 'info-circle'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-{{ $icon }} mt-1"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
{{-- Workflow errors raised outside of a form field (e.g. queue moves, check-in). --}}
@foreach (['patient', 'status', 'verify', 'payment', 'item', 'checklist'] as $key)
    @error($key)
        <div class="alert alert-danger alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>{{ $message }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @enderror
@endforeach
