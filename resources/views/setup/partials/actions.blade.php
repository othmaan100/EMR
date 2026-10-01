@php($keys = array_keys($steps))
@php($prev = $keys[array_search($step, $keys) - 1] ?? null)
<div class="d-flex justify-content-between mt-4 pt-3 border-top">
    @if ($prev)
        <a href="{{ route('setup.step', $prev) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    @else
        <span></span>
    @endif
    <button type="submit" class="btn btn-primary px-4">{{ $label ?? 'Save & Continue' }} <i class="bi bi-arrow-right ms-1"></i></button>
</div>
