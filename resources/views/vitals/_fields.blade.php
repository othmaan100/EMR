@php
    $num = function (string $name, string $label, string $unit, string $attrs = '') {
        return compact('name', 'label', 'unit', 'attrs');
    };
    $groups = [
        'Core observations' => [
            $num('temperature', 'Temperature', '°C', 'step=0.1 min=25 max=45 inputmode=decimal'),
            $num('pulse', 'Pulse', '/min', 'min=20 max=300 inputmode=numeric'),
            $num('respiratory_rate', 'Respiratory rate', '/min', 'min=4 max=80 inputmode=numeric'),
            $num('spo2', 'SpO₂', '%', 'min=50 max=100 inputmode=numeric'),
        ],
        'Body measurements & other' => [
            $num('weight', 'Weight', 'kg', 'step=0.01 min=0.3 max=400 inputmode=decimal'),
            $num('height', 'Height', 'cm', 'step=0.1 min=20 max=250 inputmode=decimal'),
            $num('blood_glucose', 'Blood glucose', 'mmol/L', 'step=0.1 min=0.5 max=50 inputmode=decimal'),
            $num('pain_score', 'Pain score', '/10', 'min=0 max=10 inputmode=numeric'),
        ],
    ];
@endphp

@foreach ($groups as $heading => $fields)
    <h6 class="text-muted text-uppercase small mt-2 mb-2">{{ $heading }}</h6>
    <div class="row g-3 mb-3">
        @if ($loop->first)
            <div class="col-md-4 col-lg-3">
                <label class="form-label" for="systolic">Blood pressure</label>
                <div class="input-group">
                    <input type="number" id="systolic" name="systolic" value="{{ old('systolic') }}" min="40" max="300" inputmode="numeric"
                           @class(['form-control', 'is-invalid' => $errors->has('systolic')]) placeholder="Sys" aria-label="Systolic">
                    <span class="input-group-text">/</span>
                    <input type="number" id="diastolic" name="diastolic" value="{{ old('diastolic') }}" min="20" max="200" inputmode="numeric"
                           @class(['form-control', 'is-invalid' => $errors->has('diastolic')]) placeholder="Dia" aria-label="Diastolic">
                    <span class="input-group-text">mmHg</span>
                </div>
                @error('systolic')<div class="text-danger small">{{ $message }}</div>@enderror
                @error('diastolic')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        @endif
        @foreach ($fields as $f)
            <div class="col-6 col-md-4 col-lg-{{ $loop->parent->first ? 2 : 3 }}">
                <label class="form-label" for="{{ $f['name'] }}">{{ $f['label'] }}</label>
                <div class="input-group">
                    <input type="number" id="{{ $f['name'] }}" name="{{ $f['name'] }}" value="{{ old($f['name']) }}" {!! $f['attrs'] !!}
                           @class(['form-control', 'is-invalid' => $errors->has($f['name'])])>
                    <span class="input-group-text">{{ $f['unit'] }}</span>
                </div>
                @error($f['name'])<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        @endforeach
        @if ($loop->last)
            <div class="col-6 col-md-4 col-lg-3">
                <label class="form-label">BMI</label>
                <div class="form-control-plaintext fw-semibold"><span id="bmi-preview">—</span> kg/m²</div>
            </div>
        @endif
    </div>
@endforeach

<div class="row g-3 mb-3">
    <x-form.select name="consciousness" label="Level of consciousness (AVPU)" :options="\App\Models\VitalSign::CONSCIOUSNESS" value="A" col="col-md-4" />
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="on_oxygen" value="0">
            <input class="form-check-input" type="checkbox" name="on_oxygen" value="1" id="on_oxygen" @checked(old('on_oxygen'))>
            <label class="form-check-label" for="on_oxygen">Patient is on supplemental oxygen</label>
        </div>
    </div>
    <x-form.input name="notes" label="Comment on readings" col="col-md-4" placeholder="e.g. BP taken twice, left arm" />
</div>
<div class="form-text mb-3">NEWS2 early-warning score is calculated automatically for adults when temperature, BP, pulse, respiratory rate and SpO₂ are all entered.</div>
