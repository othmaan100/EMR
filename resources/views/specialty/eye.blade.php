@extends('layouts.app')

@section('title', 'Eye examinations — '.$patient->hospital_number)

@section('content')
@php
    $snellen = array_combine(config('emr.specialty.snellen'), config('emr.specialty.snellen'));
    $canRecord = auth()->user()->can('eye.record');
@endphp

@include('patients._mini-banner')

<div class="row g-3">
    <div class="col-xl-5 order-xl-2">
        @forelse ($exams as $exam)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ format_date($exam->created_at, true) }} <span class="small text-muted">· {{ $exam->examiner?->name }}</span></span>
                    @if ($exam->spectacles_prescribed)
                        <a href="{{ route('specialty.spectacles', $exam) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Spectacle Rx</a>
                    @endif
                </div>
                <div class="card-body small">
                    @foreach ($exam->alerts() as $alert)
                        <div class="alert alert-danger py-1 px-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ $alert }}</div>
                    @endforeach
                    <table class="table table-sm table-bordered mb-2">
                        <thead class="table-light"><tr><th></th><th>Right (OD)</th><th>Left (OS)</th></tr></thead>
                        <tbody>
                            <tr><th class="fw-normal text-muted">VA unaided</th><td>{{ $exam->va_right ?? '—' }}</td><td>{{ $exam->va_left ?? '—' }}</td></tr>
                            <tr><th class="fw-normal text-muted">VA pinhole / corrected</th><td>{{ $exam->va_right_corrected ?? '—' }}</td><td>{{ $exam->va_left_corrected ?? '—' }}</td></tr>
                            <tr><th class="fw-normal text-muted">IOP (mmHg)</th>
                                <td @class(['text-danger fw-semibold' => $exam->iop_right > \App\Models\EyeExam::IOP_HIGH])>{{ $exam->iop_right ?? '—' }}</td>
                                <td @class(['text-danger fw-semibold' => $exam->iop_left > \App\Models\EyeExam::IOP_HIGH])>{{ $exam->iop_left ?? '—' }}</td></tr>
                            <tr><th class="fw-normal text-muted">Refraction</th><td>{{ $exam->rx('right') }}</td><td>{{ $exam->rx('left') }}</td></tr>
                            @if ($exam->anterior_right || $exam->anterior_left)
                                <tr><th class="fw-normal text-muted">Anterior segment</th><td>{{ $exam->anterior_right }}</td><td>{{ $exam->anterior_left }}</td></tr>
                            @endif
                            @if ($exam->fundus_right || $exam->fundus_left)
                                <tr><th class="fw-normal text-muted">Fundus</th><td>{{ $exam->fundus_right }}</td><td>{{ $exam->fundus_left }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                    @if ($exam->diagnosis)<div><span class="text-muted">Diagnosis:</span> {{ $exam->diagnosis }}</div>@endif
                    @if ($exam->plan)<div><span class="text-muted">Plan:</span> {{ $exam->plan }}</div>@endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-muted">No eye examinations yet.</div></div>
        @endforelse
    </div>

    <div class="col-xl-7 order-xl-1">
        @if ($canRecord && ! $patient->is_deceased)
            <form method="POST" action="{{ route('specialty.eye.store', $patient) }}" class="card">
                @csrf
                <div class="card-header"><i class="bi bi-eye me-1"></i> New eye examination</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th></th><th>Right eye (OD)</th><th>Left eye (OS)</th></tr></thead>
                            <tbody>
                                <tr><th class="fw-normal small">Visual acuity — unaided</th>
                                    <td><x-form.select name="va_right" label="" :options="$snellen" placeholder="—" aria-label="Right unaided" class="form-select-sm" /></td>
                                    <td><x-form.select name="va_left" label="" :options="$snellen" placeholder="—" aria-label="Left unaided" class="form-select-sm" /></td></tr>
                                <tr><th class="fw-normal small">Pinhole / with glasses</th>
                                    <td><x-form.select name="va_right_corrected" label="" :options="$snellen" placeholder="—" aria-label="Right corrected" class="form-select-sm" /></td>
                                    <td><x-form.select name="va_left_corrected" label="" :options="$snellen" placeholder="—" aria-label="Left corrected" class="form-select-sm" /></td></tr>
                                <tr><th class="fw-normal small">Eye pressure (mmHg)</th>
                                    <td><x-form.input name="iop_right" type="number" label="" step="0.1" min="0" max="80" aria-label="Right IOP" class="form-control-sm" /></td>
                                    <td><x-form.input name="iop_left" type="number" label="" step="0.1" min="0" max="80" aria-label="Left IOP" class="form-control-sm" /></td></tr>
                                @foreach (['sph' => ['Sphere (D)', 0.25, -30, 30], 'cyl' => ['Cylinder (D)', 0.25, -10, 10], 'axis' => ['Axis (°)', 1, 0, 180], 'add' => ['Near add (D)', 0.25, 0, 4]] as $f => [$label, $step, $min, $max])
                                    <tr><th class="fw-normal small">{{ $label }}</th>
                                        <td><x-form.input :name="$f.'_right'" type="number" label="" :step="$step" :min="$min" :max="$max" :aria-label="'Right '.$label" class="form-control-sm" /></td>
                                        <td><x-form.input :name="$f.'_left'" type="number" label="" :step="$step" :min="$min" :max="$max" :aria-label="'Left '.$label" class="form-control-sm" /></td></tr>
                                @endforeach
                                <tr><th class="fw-normal small">Anterior segment</th>
                                    <td><x-form.textarea name="anterior_right" label="" rows="2" aria-label="Right anterior segment" /></td>
                                    <td><x-form.textarea name="anterior_left" label="" rows="2" aria-label="Left anterior segment" /></td></tr>
                                <tr><th class="fw-normal small">Fundus / posterior</th>
                                    <td><x-form.textarea name="fundus_right" label="" rows="2" aria-label="Right fundus" /></td>
                                    <td><x-form.textarea name="fundus_left" label="" rows="2" aria-label="Left fundus" /></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="row g-3">
                        <x-form.input name="diagnosis" label="Diagnosis" col="col-md-8" maxlength="255" placeholder="e.g. Myopia, presbyopia, cataract, glaucoma suspect" />
                        <x-form.input name="pd" type="number" label="PD (mm)" col="col-md-4" min="40" max="80" />
                        <x-form.textarea name="plan" label="Plan" col="col-12" rows="2" />
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="hidden" name="spectacles_prescribed" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="spectacles_prescribed" value="1" id="spectacles_prescribed" @checked(old('spectacles_prescribed'))>
                                <label class="form-check-label" for="spectacles_prescribed">Prescribe spectacles</label>
                            </div>
                        </div>
                        <x-form.input name="lens_notes" label="Lens notes" col="col-md-6" maxlength="255" placeholder="e.g. Photochromic, bifocal" />
                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="charge_refraction" value="0">
                                <input class="form-check-input" type="checkbox" name="charge_refraction" value="1" id="charge_refraction" @checked(old('charge_refraction', true))>
                                <label class="form-check-label" for="charge_refraction">Charge refraction / eye test fee (if priced)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Save examination</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
