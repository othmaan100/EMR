<div class="card mb-3" id="diagnoses">
    <div class="card-header"><i class="bi bi-clipboard2-check me-1"></i> Diagnoses @if ($editable)<span class="text-danger">*</span>@endif</div>
    <ul class="list-group list-group-flush">
        @forelse ($consultation->diagnoses as $dx)
            <li class="list-group-item d-flex align-items-center gap-2">
                @if ($dx->is_primary)<i class="bi bi-star-fill text-warning" title="Primary diagnosis" aria-label="Primary"></i>@endif
                <span class="badge text-bg-light border font-monospace">{{ $dx->icd10_code ?? '—' }}</span>
                <span class="flex-grow-1">{{ $dx->description }}</span>
                <span class="badge text-bg-{{ \App\Models\Diagnosis::CERTAINTY[$dx->certainty]['color'] }}">{{ \App\Models\Diagnosis::CERTAINTY[$dx->certainty]['label'] }}</span>
                @if ($editable)
                    <form method="POST" action="{{ route('diagnoses.destroy', $dx) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger p-0" title="Remove" aria-label="Remove diagnosis"><i class="bi bi-x-lg"></i></button>
                    </form>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted small">No diagnosis recorded.</li>
        @endforelse
    </ul>
    @if ($editable)
        <div class="card-body border-top">
            <form method="POST" action="{{ route('consultations.diagnoses.store', $consultation) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label for="dx_description" class="form-label small mb-1">Diagnosis (search ICD-10 or type free text)</label>
                    <div data-icd-lookup data-url="{{ route('icd10.search') }}" class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text text-muted" data-icd-badge>no code</span>
                            <input type="text" id="dx_description" name="description" data-icd-input autocomplete="off" required maxlength="255"
                                   value="{{ old('description') }}" placeholder="e.g. malaria, J18.9…"
                                   @class(['form-control', 'is-invalid' => $errors->diagnosis->has('description')])>
                        </div>
                        <input type="hidden" name="icd10_code" value="{{ old('icd10_code') }}">
                        <div data-icd-results class="list-group position-absolute w-100 shadow-sm small" style="z-index: 20; max-height: 300px; overflow-y: auto;"></div>
                        @if ($errors->diagnosis->any())<div class="text-danger small">{{ $errors->diagnosis->first() }}</div>@endif
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="certainty" class="form-label small mb-1">Certainty</label>
                    <select id="certainty" name="certainty" class="form-select">
                        @foreach (\App\Models\Diagnosis::CERTAINTY as $key => $c)
                            <option value="{{ $key }}">{{ $c['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 align-items-center">
                    <div class="form-check mb-0">
                        <input type="hidden" name="is_primary" value="0">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="is_primary">
                        <label class="form-check-label small" for="is_primary">Primary</label>
                    </div>
                    <button class="btn btn-outline-primary ms-auto"><i class="bi bi-plus-lg"></i> Add</button>
                </div>
            </form>
        </div>
    @endif
</div>
