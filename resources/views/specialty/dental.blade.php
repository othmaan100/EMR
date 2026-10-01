@extends('layouts.app')

@section('title', 'Dental chart — '.$patient->hospital_number)

@section('content')
@php
    $conditions = config('emr.specialty.dental_conditions');
    $teeth = config('emr.specialty.teeth');
    $canRecord = auth()->user()->can('dental.record');
    // Dark fills get white numbers so the tooth number stays readable.
    $ink = function (string $hex): string {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150 ? '#fff' : '#212529';
    };
    $arches = ['permanent' => 'Permanent teeth'];
    if ($showPrimary) {
        $arches['primary'] = 'Primary (milk) teeth';
    }
@endphp

@include('patients._mini-banner')

<style>
    .tooth { width: 2.4rem; height: 2.6rem; border: 1px solid #ced4da; border-radius: .4rem; font-size: .8rem; font-weight: 600; position: relative; padding: 0; }
    .tooth.planned::after { content: ''; position: absolute; top: 2px; right: 2px; width: 8px; height: 8px; border-radius: 50%; background: #ffc107; border: 1px solid #997404; }
    .tooth:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
    .arch-gap { width: 1rem; border-left: 2px dashed #adb5bd; }
    .swatch { display: inline-block; width: .9rem; height: .9rem; border: 1px solid #ced4da; border-radius: .2rem; vertical-align: -2px; }
</style>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-emoji-smile me-1"></i> Dental chart <span class="small text-muted">(FDI numbering, as you face the patient)</span></span>
            </div>
            <div class="card-body">
                @foreach ($arches as $set => $title)
                    <div class="small text-muted mb-1">{{ $title }}</div>
                    <div class="overflow-auto mb-3">
                        @foreach ([[0, 1], [2, 3]] as $rowIndex => [$q1, $q2])
                            <div class="d-flex gap-1 justify-content-center mb-1" role="group" aria-label="{{ $rowIndex === 0 ? 'Upper' : 'Lower' }} jaw">
                                @foreach ([$teeth[$set][$q1], $teeth[$set][$q2]] as $i => $quadrant)
                                    @if ($i === 1)<span class="arch-gap" aria-hidden="true"></span>@endif
                                    @foreach ($quadrant as $tooth)
                                        @php
                                            $state = $toothMap[$tooth] ?? ['condition' => null, 'planned' => false];
                                            $fill = $state['condition'] ? $conditions[$state['condition']][1] : '#ffffff';
                                            $label = 'Tooth '.$tooth.': '.($state['condition'] ? $conditions[$state['condition']][0] : 'nothing recorded').($state['planned'] ? ', treatment planned' : '');
                                        @endphp
                                        <button type="button" @class(['tooth', 'planned' => $state['planned']]) data-tooth="{{ $tooth }}"
                                                style="background: {{ $fill }}; color: {{ $ink($fill) }};" title="{{ $label }}" aria-label="{{ $label }}">{{ $tooth }}</button>
                                    @endforeach
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <div class="d-flex flex-wrap gap-3 small">
                    @foreach ($conditions as $key => [$label, $colour])
                        @if ($key !== 'sound')<span><span class="swatch" style="background: {{ $colour }}"></span> {{ $label }}</span>@endif
                    @endforeach
                    <span><span class="swatch" style="background: #ffc107; border-radius: 50%"></span> Treatment planned</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Findings &amp; treatment</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Tooth</th><th>Condition</th><th>Treatment</th><th>Status</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($findings as $f)
                            <tr @class(['text-muted' => $f->status === 'cancelled'])>
                                <td class="ps-3 small text-nowrap">{{ format_date($f->created_at) }}<span class="d-block text-muted">{{ $f->recorder?->name }}</span></td>
                                <td class="small">{{ $f->toothLabel() }} @if ($f->surfaces)<span class="text-muted">({{ $f->surfaces }})</span>@endif</td>
                                <td class="small">{{ $f->conditionLabel() ?? '—' }}</td>
                                <td class="small">{{ $f->service?->name ?? '—' }}</td>
                                <td class="small">
                                    <span class="badge text-bg-{{ \App\Models\DentalFinding::STATUSES[$f->status]['color'] }}">{{ \App\Models\DentalFinding::STATUSES[$f->status]['label'] }}</span>
                                    @if ($f->completed_at)<span class="d-block text-muted">{{ format_date($f->completed_at) }}</span>@endif
                                </td>
                                <td class="small">{{ $f->notes }}</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if ($canRecord && $f->status === 'planned')
                                        <form method="POST" action="{{ route('specialty.dental.complete', $f) }}" class="d-inline">@csrf @method('PATCH')
                                            <button class="btn btn-sm btn-success" title="Mark done (charges the treatment)">Done</button></form>
                                        <form method="POST" action="{{ route('specialty.dental.cancel', $f) }}" class="d-inline">@csrf @method('PATCH')
                                            <button class="btn btn-sm btn-link text-danger" title="Cancel plan"><i class="bi bi-x-lg"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Nothing charted yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @if ($canRecord && ! $patient->is_deceased)
            <div class="card" id="dental-form">
                <div class="card-header">Add to chart</div>
                <div class="card-body">
                    @error('status')<div class="alert alert-danger small">{{ $message }}</div>@enderror
                    <form method="POST" action="{{ route('specialty.dental.store', $patient) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="tooth" class="form-label">Tooth</label>
                            <select id="tooth" name="tooth" @class(['form-select', 'is-invalid' => $errors->has('tooth')])>
                                <option value="">Whole mouth</option>
                                @foreach ($arches as $set => $title)
                                    <optgroup label="{{ $title }}">
                                        @foreach (collect($teeth[$set])->flatten()->sort() as $t)<option value="{{ $t }}" @selected(old('tooth') == $t)>{{ $t }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="form-text">Or click a tooth on the chart.</div>
                            @error('tooth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Surfaces</legend>
                            @foreach (config('emr.specialty.surfaces') as $code => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="surfaces[]" value="{{ $code }}" id="surface_{{ $code }}" @checked(in_array($code, old('surfaces', [])))>
                                    <label class="form-check-label" for="surface_{{ $code }}" title="{{ $label }}">{{ $code }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                        <x-form.select name="status" label="Type" :options="['existing' => 'Finding (what you see)', 'planned' => 'Treatment plan', 'completed' => 'Treatment done today']" value="existing" required />
                        <x-form.select name="condition" label="Condition" :options="collect($conditions)->map(fn ($c) => $c[0])->all()" placeholder="—"
                                       help="For treatment: the tooth's state once done (e.g. Filled, Extracted)." />
                        <x-form.select name="service_id" label="Treatment" :options="$services->all()" placeholder="—" help="Charged when done (if priced)." />
                        <x-form.input name="notes" label="Notes" maxlength="500" />
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    document.querySelectorAll('.tooth').forEach((b) => b.addEventListener('click', () => {
        const select = document.getElementById('tooth');
        if (!select) return;
        select.value = b.dataset.tooth;
        select.focus();
    }));
</script>
@endsection
