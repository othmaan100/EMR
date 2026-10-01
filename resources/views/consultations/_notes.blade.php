<div class="card mb-3" id="notes">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-text me-1"></i> Clinical notes</span>
        @if ($editable)<small class="text-muted fw-normal">Save often — notes lock when signed</small>@endif
    </div>
    <div class="card-body">
        @if ($editable)
            <form method="POST" action="{{ route('consultations.update', $consultation) }}">
                @csrf @method('PUT')
                @foreach (\App\Models\Consultation::SECTIONS as $field => [$label, $placeholder])
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label fw-semibold small mb-1">
                            {{ $label }} @if ($field === 'presenting_complaint')<span class="text-danger">*</span>@endif
                        </label>
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ in_array($field, ['history', 'examination', 'plan']) ? 4 : 2 }}"
                                  placeholder="{{ $placeholder }}"
                                  @class(['form-control', 'is-invalid' => $errors->has($field)])>{{ old($field, $consultation->{$field}) }}</textarea>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="text-end">
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i> Save notes</button>
                </div>
            </form>
        @else
            @php
                $filled = collect(\App\Models\Consultation::SECTIONS)->filter(fn ($s, $field) => filled($consultation->{$field}));
            @endphp
            @forelse ($filled as $field => [$label])
                <div class="mb-3">
                    <div class="fw-semibold small text-muted">{{ $label }}</div>
                    <div style="white-space: pre-line;">{{ $consultation->{$field} }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">No notes written yet.</p>
            @endforelse
        @endif
    </div>
</div>
