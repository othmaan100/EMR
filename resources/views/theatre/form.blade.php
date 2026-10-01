@extends('layouts.app')

@section('title', $surgery->exists ? 'Edit Booking — '.$surgery->surgery_number : 'Book Operation')

@section('content')
<div class="mx-auto" style="max-width: 900px;">
    @include('patients._mini-banner')
    @error('patient')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <form method="POST" action="{{ $surgery->exists ? route('theatre.update', $surgery) : route('theatre.store', $patient) }}" class="card">
        @csrf
        @if ($surgery->exists) @method('PUT') @endif
        @if ($surgery->pregnancy_id)<input type="hidden" name="pregnancy_id" value="{{ $surgery->pregnancy_id }}">@endif
        <div class="card-header">Operation</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-7">
                    <label for="surgical_procedure_id" class="form-label">Procedure <span class="text-danger">*</span></label>
                    <select id="surgical_procedure_id" name="surgical_procedure_id" @class(['form-select', 'is-invalid' => $errors->has('surgical_procedure_id')])>
                        <option value="">— Choose, or type below —</option>
                        @foreach ($procedures->groupBy('specialty') as $specialty => $group)
                            <optgroup label="{{ $specialty }}">
                                @foreach ($group as $p)
                                    <option value="{{ $p->id }}" data-minutes="{{ $p->typical_minutes }}" @selected(old('surgical_procedure_id', $surgery->surgical_procedure_id) == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('surgical_procedure_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <x-form.input name="procedure_name" label="Procedure name (as it will appear)" :value="$surgery->procedure_name" col="col-md-5" placeholder="Defaults to the catalogue name" />
                <x-form.select name="urgency" label="Urgency" :options="collect(\App\Models\Surgery::URGENCY)->map(fn ($u) => $u['label'])->all()" :value="$surgery->urgency" required col="col-md-4" />
                <x-form.input name="scheduled_at" type="datetime-local" label="Date & time" :value="$surgery->scheduled_at?->format('Y-m-d\TH:i')" required col="col-md-4" />
                <x-form.input name="estimated_minutes" type="number" label="Expected duration (min)" :value="$surgery->estimated_minutes" required col="col-md-4" min="5" max="1440" />
                <x-form.select name="theatre_id" label="Theatre" :options="$theatres->all()" :value="$surgery->theatre_id ?? $theatres->keys()->first()" required col="col-md-4" />
                <x-form.select name="surgeon_id" label="Surgeon" :options="$surgeons->all()" :value="$surgery->surgeon_id" placeholder="—" col="col-md-4" />
                <x-form.select name="anaesthetist_id" label="Anaesthetist" :options="$anaesthetists->all()" :value="$surgery->anaesthetist_id" placeholder="—" col="col-md-4" />
                <x-form.input name="assistant" label="Assistant(s)" :value="$surgery->assistant" col="col-md-12" />
                <x-form.textarea name="indication" label="Indication / pre-operative diagnosis" :value="$surgery->indication" required col="col-12" rows="2" />
            </div>
            @error('scheduled_at')<div class="alert alert-danger small mt-3 mb-0">{{ $message }}</div>@enderror
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ $surgery->exists ? route('theatre.show', $surgery) : route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary px-4"><i class="bi bi-calendar-check me-1"></i> {{ $surgery->exists ? 'Save booking' : 'Book operation' }}</button>
        </div>
    </form>
</div>
<script>
    // Pre-fill the expected duration from the catalogue.
    document.getElementById('surgical_procedure_id').addEventListener('change', (e) => {
        const minutes = e.target.selectedOptions[0]?.dataset.minutes;
        if (minutes) document.getElementById('estimated_minutes').value = minutes;
    });
</script>
@endsection
