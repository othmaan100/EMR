@extends('layouts.app')

@section('title', 'Record Delivery')

@section('content')
@php
    $m = config('emr.maternity');
    $babies = old('babies', [['sex' => '', 'outcome' => 'live_birth']]);
@endphp
<div class="mx-auto" style="max-width: 980px;">
    @include('patients._mini-banner')

    <form method="POST" action="{{ route('maternity.delivery.store', $pregnancy) }}">
        @csrf
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="card mb-3">
            <div class="card-header">Delivery · {{ $pregnancy->obstetricFormula() }} · {{ $pregnancy->gestationLabel() }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.input name="delivered_at" type="datetime-local" label="Date & time of birth" :value="now()->format('Y-m-d\TH:i')" required col="col-md-4"
                                  max="{{ now()->format('Y-m-d\TH:i') }}" />
                    <x-form.select name="mode" label="Mode of delivery" :options="$m['delivery_modes']" value="svd" required col="col-md-5" />
                    <x-form.input name="blood_loss_ml" type="number" label="Blood loss (ml)" col="col-md-3" min="0" max="10000" help="≥ 500 ml is recorded as PPH" />
                    <x-form.select name="perineum" label="Perineum" :options="$m['perineum']" placeholder="—" col="col-md-4" />
                    <x-form.select name="maternal_outcome" label="Mother" :options="['alive' => 'Alive', 'died' => 'Died']" value="alive" required col="col-md-4" />
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="hidden" name="placenta_complete" value="0">
                            <input class="form-check-input" type="checkbox" name="placenta_complete" value="1" id="placenta" @checked(old('placenta_complete', true))>
                            <label class="form-check-label" for="placenta">Placenta & membranes complete</label>
                        </div>
                    </div>
                </div>
                <label class="form-label mt-3">Complications</label>
                <div>
                    @foreach ($m['complications'] as $key => $label)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="complications[]" value="{{ $key }}" id="c-{{ $key }}" @checked(in_array($key, old('complications', [])))>
                            <label class="form-check-label small" for="c-{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                <x-form.textarea name="notes" label="Delivery notes" col="mt-3" rows="2" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                Baby / babies
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-baby"><i class="bi bi-plus-lg"></i> Add baby (twins…)</button>
            </div>
            <div class="card-body" id="babies">
                @foreach ($babies as $i => $b)
                    <div class="row g-2 align-items-end border-bottom pb-3 mb-3" data-baby>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Sex</label>
                            <select name="babies[{{ $i }}][sex]" class="form-select form-select-sm" required>
                                <option value="">—</option><option value="female" @selected(($b['sex'] ?? '') === 'female')>Female</option><option value="male" @selected(($b['sex'] ?? '') === 'male')>Male</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Outcome</label>
                            <select name="babies[{{ $i }}][outcome]" class="form-select form-select-sm">
                                @foreach ($m['baby_outcomes'] as $k => $l)<option value="{{ $k }}" @selected(($b['outcome'] ?? '') === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><label class="form-label small mb-1">Weight (g)</label><input type="number" name="babies[{{ $i }}][birth_weight_g]" value="{{ $b['birth_weight_g'] ?? '' }}" min="300" max="7000" class="form-control form-control-sm"></div>
                        <div class="col-md-1"><label class="form-label small mb-1">Apgar 1</label><input type="number" name="babies[{{ $i }}][apgar_1]" value="{{ $b['apgar_1'] ?? '' }}" min="0" max="10" class="form-control form-control-sm"></div>
                        <div class="col-md-1"><label class="form-label small mb-1">Apgar 5</label><input type="number" name="babies[{{ $i }}][apgar_5]" value="{{ $b['apgar_5'] ?? '' }}" min="0" max="10" class="form-control form-control-sm"></div>
                        <div class="col-md-2"><label class="form-label small mb-1">First name (optional)</label><input type="text" name="babies[{{ $i }}][name]" value="{{ $b['name'] ?? '' }}" maxlength="100" class="form-control form-control-sm" placeholder="Baby"></div>
                        <div class="col-md-1">
                            <div class="form-check"><input type="hidden" name="babies[{{ $i }}][resuscitated]" value="0"><input class="form-check-input" type="checkbox" name="babies[{{ $i }}][resuscitated]" value="1" id="res-{{ $i }}" @checked($b['resuscitated'] ?? false)><label class="form-check-label small" for="res-{{ $i }}">Resus.</label></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer bg-white small text-muted">Live-born babies are registered automatically as patients (surname {{ $patient->last_name }}), linked to the mother, with her address, next of kin and payer.</div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('maternity.show', $pregnancy) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-success px-4" onclick="return confirm('Record this delivery? The pregnancy will be closed.')"><i class="bi bi-balloon-heart me-1"></i> Record delivery</button>
        </div>
    </form>
</div>
<script>
    document.getElementById('add-baby').addEventListener('click', () => {
        const rows = document.querySelectorAll('[data-baby]');
        const clone = rows[rows.length - 1].cloneNode(true);
        const i = rows.length;
        clone.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/babies\[\d+\]/, `babies[${i}]`);
            if (el.type !== 'hidden' && el.type !== 'checkbox' && el.tagName !== 'SELECT') el.value = '';
            if (el.type === 'checkbox') el.checked = false;
        });
        clone.querySelectorAll('[id]').forEach((el) => (el.id = el.id.replace(/\d+$/, i)));
        clone.querySelectorAll('label[for]').forEach((el) => (el.htmlFor = el.htmlFor.replace(/\d+$/, i)));
        document.getElementById('babies').appendChild(clone);
    });
</script>
@endsection
