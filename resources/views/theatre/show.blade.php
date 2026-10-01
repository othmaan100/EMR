@extends('layouts.app')

@section('title', $surgery->surgery_number.' — '.$surgery->procedure_name)

@section('content')
@php
    $user = auth()->user();
    $canManage = $user->can('theatre.manage');
    $open = in_array($surgery->status, ['scheduled', 'ready', 'postponed'], true);
    $next = $surgery->nextPhase();
    $u = \App\Models\Surgery::URGENCY[$surgery->urgency];
@endphp

@include('patients._mini-banner')

<div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>
        <strong>{{ $surgery->procedure_name }}</strong>
        <span class="badge text-bg-{{ $u['color'] }}">{{ $u['label'] }}</span>
        <span class="badge text-bg-{{ $surgery->statusColor() }}">{{ $surgery->statusLabel() }}</span>
        · {{ $surgery->theatre->name }} · {{ format_date($surgery->scheduled_at, true) }} ({{ $surgery->estimated_minutes }} min)
        @if ($surgery->cancel_reason)<span class="text-danger">· {{ $surgery->cancel_reason }}</span>@endif
    </span>
    <span class="d-flex gap-2">
        @if ($surgery->status === 'completed')
            <a href="{{ route('theatre.note', $surgery) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Operation note</a>
        @endif
        @can('theatre.book')
            @if ($open)
                <a href="{{ route('theatre.edit', $surgery) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit / reschedule</a>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#stopModal">Postpone / cancel</button>
            @endif
        @endcan
    </span>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        {{-- ============ Pre-op ============ --}}
        <div class="card mb-3" id="preop">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-clipboard-check me-1"></i> Pre-operative assessment</span>
                @if ($surgery->assessed_at)<small class="text-muted fw-normal">{{ $surgery->assessor?->name }}, {{ format_date($surgery->assessed_at, true) }}</small>@endif
            </div>
            <div class="card-body">
                @if ($canManage && in_array($surgery->status, ['scheduled', 'ready'], true))
                    <form method="POST" action="{{ route('theatre.preop', $surgery) }}" class="row g-2">
                        @csrf
                        @foreach (['consent_signed' => 'Consent signed', 'fasting_confirmed' => 'Fasting confirmed', 'site_marked' => 'Operation site marked'] as $f => $label)
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="{{ $f }}" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="{{ $f }}" value="1" id="{{ $f }}" @checked($surgery->{$f})>
                                    <label class="form-check-label" for="{{ $f }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                        <div class="col-md-4">
                            <label for="asa_grade" class="form-label small mb-1">ASA grade</label>
                            <select id="asa_grade" name="asa_grade" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ([1 => 'I — healthy', 2 => 'II — mild systemic disease', 3 => 'III — severe systemic disease', 4 => 'IV — constant threat to life', 5 => 'V — moribund', 6 => 'VI — brain-dead donor'] as $g => $l)
                                    <option value="{{ $g }}" @selected($surgery->asa_grade == $g)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label for="blood" class="form-label small mb-1">Blood units available</label><input type="number" id="blood" name="blood_units_available" min="0" max="20" value="{{ $surgery->blood_units_available }}" class="form-control form-control-sm"></div>
                        <div class="col-md-5"><label for="preop_notes" class="form-label small mb-1">Notes (airway, investigations, drugs to stop…)</label><input type="text" id="preop_notes" name="preop_notes" value="{{ $surgery->preop_notes }}" maxlength="3000" class="form-control form-control-sm"></div>
                        <div class="col-12 text-end"><button class="btn btn-sm btn-primary">Save assessment</button></div>
                    </form>
                @else
                    <div class="d-flex flex-wrap gap-2 small">
                        @foreach (['consent_signed' => 'Consent', 'fasting_confirmed' => 'Fasting', 'site_marked' => 'Site marked'] as $f => $label)
                            <span class="badge {{ $surgery->{$f} ? 'text-bg-success' : 'text-bg-light border' }}"><i class="bi {{ $surgery->{$f} ? 'bi-check-lg' : 'bi-dash' }}"></i> {{ $label }}</span>
                        @endforeach
                        <span class="badge text-bg-light border">ASA {{ $surgery->asa_grade ? ['', 'I', 'II', 'III', 'IV', 'V', 'VI'][$surgery->asa_grade] : '—' }}</span>
                        <span class="badge text-bg-light border">Blood: {{ $surgery->blood_units_available ?? '—' }} unit(s)</span>
                    </div>
                    @if ($surgery->preop_notes)<div class="small mt-2">{{ $surgery->preop_notes }}</div>@endif
                @endif
            </div>
        </div>

        {{-- ============ WHO checklist ============ --}}
        <div class="card mb-3" id="checklist">
            <div class="card-header"><i class="bi bi-list-check me-1"></i> WHO Surgical Safety Checklist</div>
            <div class="card-body">
                @error('checklist')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                <div class="row g-3">
                    @foreach (\App\Models\Surgery::CHECKLIST as $phase => $def)
                        @php
                            $done = $surgery->phaseDone($phase);
                            $isNext = $next === $phase && ! in_array($surgery->status, ['cancelled', 'postponed', 'completed'], true);
                            $record = $surgery->checklist[$phase] ?? null;
                        @endphp
                        <div class="col-lg-4">
                            <div @class(['border rounded p-2 h-100', 'border-success bg-success-subtle' => $done, 'border-primary' => $isNext])>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ $def['title'] }}</strong>
                                    @if ($done)<span class="badge text-bg-success"><i class="bi bi-check-lg"></i> Done</span>
                                    @elseif ($isNext)<span class="badge text-bg-primary">Next</span>
                                    @else<span class="badge text-bg-light border">Locked</span>@endif
                                </div>
                                <div class="small text-muted mb-2">{{ $def['when'] }}</div>
                                @if ($done)
                                    <ul class="small ps-3 mb-1">@foreach ($record['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                    <div class="small text-muted">{{ $record['by_name'] }}, {{ \Illuminate\Support\Carbon::parse($record['at'])->format('d M H:i') }}</div>
                                @elseif ($isNext && $canManage)
                                    <form method="POST" action="{{ route('theatre.checklist', [$surgery, $phase]) }}">
                                        @csrf
                                        @foreach ($def['items'] as $i => $item)
                                            <div class="form-check small">
                                                <input class="form-check-input" type="checkbox" name="items[]" value="{{ $i }}" id="{{ $phase }}-{{ $i }}" required>
                                                <label class="form-check-label" for="{{ $phase }}-{{ $i }}">{{ $item }}</label>
                                            </div>
                                        @endforeach
                                        <button class="btn btn-sm btn-primary w-100 mt-2">Confirm {{ $def['title'] }}</button>
                                    </form>
                                @else
                                    <ul class="small ps-3 mb-0 text-muted">@foreach ($def['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($next === 'sign_in' && $surgery->status === 'scheduled')
                    <p class="small text-muted mt-2 mb-0">Sign in unlocks once the pre-operative assessment records consent, fasting and an ASA grade.</p>
                @endif
            </div>
        </div>

        {{-- ============ Anaesthesia ============ --}}
        <div class="card mb-3" id="anaesthesia">
            <div class="card-header"><i class="bi bi-lungs me-1"></i> Anaesthesia record</div>
            <div class="card-body">
                @if ($canManage && in_array($surgery->status, ['ready', 'in_theatre'], true))
                    <form method="POST" action="{{ route('theatre.anaesthesia', $surgery) }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-4"><label for="anaesthesia_type" class="form-label small mb-1">Type</label>
                            <select id="anaesthesia_type" name="anaesthesia_type" class="form-select form-select-sm"><option value="">—</option>
                                @foreach (\App\Models\Surgery::ANAESTHESIA as $k => $l)<option value="{{ $k }}" @selected($surgery->anaesthesia_type === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="col-md-8"><label for="airway" class="form-label small mb-1">Airway</label><input type="text" id="airway" name="airway" value="{{ $surgery->airway }}" maxlength="50" class="form-control form-control-sm" placeholder="e.g. ETT 7.0, LMA, face mask"></div>
                        <div class="col-md-6"><label for="anaesthesia_drugs" class="form-label small mb-1">Drugs</label><textarea id="anaesthesia_drugs" name="anaesthesia_drugs" rows="2" class="form-control form-control-sm">{{ $surgery->anaesthesia_drugs }}</textarea></div>
                        <div class="col-md-6"><label for="fluids" class="form-label small mb-1">Fluids / blood</label><textarea id="fluids" name="fluids" rows="2" class="form-control form-control-sm">{{ $surgery->fluids }}</textarea></div>
                        <div class="col-md-10"><input type="text" name="anaesthesia_notes" value="{{ $surgery->anaesthesia_notes }}" maxlength="3000" class="form-control form-control-sm" placeholder="Notes / events" aria-label="Anaesthesia notes"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Save</button></div>
                    </form>
                @else
                    <dl class="row small mb-2">
                        <dt class="col-sm-3 text-muted fw-normal">Type</dt><dd class="col-sm-9">{{ \App\Models\Surgery::ANAESTHESIA[$surgery->anaesthesia_type] ?? '—' }}{{ $surgery->airway ? ' · '.$surgery->airway : '' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Drugs</dt><dd class="col-sm-9" style="white-space: pre-line;">{{ $surgery->anaesthesia_drugs ?: '—' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Fluids</dt><dd class="col-sm-9" style="white-space: pre-line;">{{ $surgery->fluids ?: '—' }}</dd>
                        @if ($surgery->anaesthesia_notes)<dt class="col-sm-3 text-muted fw-normal">Notes</dt><dd class="col-sm-9">{{ $surgery->anaesthesia_notes }}</dd>@endif
                    </dl>
                @endif

                <table class="table table-sm small align-middle mb-2">
                    <thead class="table-light"><tr><th>Time</th><th>Pulse</th><th>BP</th><th>SpO₂</th><th>Notes</th><th>By</th></tr></thead>
                    <tbody>
                        @forelse ($surgery->observations as $o)
                            <tr @class(['table-warning' => $o->isAbnormal()])>
                                <td>{{ $o->recorded_at->format('H:i') }}</td><td>{{ $o->pulse }}</td>
                                <td>{{ $o->systolic ? $o->systolic.'/'.$o->diastolic : '' }}</td><td>{{ $o->spo2 !== null ? $o->spo2.'%' : '' }}</td>
                                <td>{{ $o->notes }}</td><td>{{ $o->recorder?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center">No intra-operative observations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($canManage && $surgery->status === 'in_theatre')
                    <form method="POST" action="{{ route('theatre.observe', $surgery) }}" class="d-flex flex-wrap gap-1">
                        @csrf
                        <input type="number" name="pulse" class="form-control form-control-sm" style="width: 6rem;" placeholder="Pulse" aria-label="Pulse">
                        <input type="number" name="systolic" class="form-control form-control-sm" style="width: 5.5rem;" placeholder="Sys" aria-label="Systolic">
                        <input type="number" name="diastolic" class="form-control form-control-sm" style="width: 5.5rem;" placeholder="Dia" aria-label="Diastolic">
                        <input type="number" name="spo2" class="form-control form-control-sm" style="width: 5.5rem;" placeholder="SpO₂" aria-label="SpO2">
                        <input type="text" name="notes" maxlength="255" class="form-control form-control-sm flex-grow-1" style="min-width: 10rem;" placeholder="Event / drug" aria-label="Notes">
                        <button class="btn btn-sm btn-outline-primary">Add</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ============ Operation note ============ --}}
        <div class="card mb-3" id="opnote">
            <div class="card-header"><i class="bi bi-file-medical me-1"></i> Operation note</div>
            <div class="card-body">
                @if ($surgery->status === 'completed')
                    <dl class="row small mb-0">
                        @foreach (['findings' => 'Findings', 'procedure_performed' => 'Procedure', 'blood_loss_ml' => 'Blood loss (ml)', 'specimens' => 'Specimens',
                                   'implants' => 'Implants', 'drains' => 'Drains', 'closure' => 'Closure', 'complications' => 'Complications', 'postop_orders' => 'Post-op orders'] as $f => $label)
                            @if (filled($surgery->{$f}))
                                <dt class="col-sm-3 text-muted fw-normal">{{ $label }}</dt><dd class="col-sm-9" style="white-space: pre-line;">{{ $surgery->{$f} }}</dd>
                            @endif
                        @endforeach
                        <dt class="col-sm-3 text-muted fw-normal">Signed</dt><dd class="col-sm-9">{{ format_date($surgery->completed_at, true) }}</dd>
                    </dl>
                @elseif ($surgery->status === 'in_theatre' && $user->can('theatre.operate'))
                    @if (! $surgery->phaseDone('sign_out'))
                        <p class="small text-muted">Complete the WHO <strong>Sign out</strong> before signing the note. You can start writing now.</p>
                    @endif
                    @if ($errors->opnote->any())<div class="alert alert-danger py-2 small">{{ $errors->opnote->first() }}</div>@endif
                    <form method="POST" action="{{ route('theatre.complete', $surgery) }}" class="row g-2">
                        @csrf
                        <x-form.textarea name="findings" label="Findings" col="col-12" rows="3" required />
                        <x-form.textarea name="procedure_performed" label="Procedure performed" :value="$surgery->procedure_name" col="col-12" rows="3" required />
                        <x-form.input name="blood_loss_ml" type="number" label="Blood loss (ml)" col="col-md-3" min="0" />
                        <x-form.input name="specimens" label="Specimens (to lab)" col="col-md-3" />
                        <x-form.input name="implants" label="Implants" col="col-md-3" />
                        <x-form.input name="drains" label="Drains" col="col-md-3" />
                        <x-form.input name="closure" label="Closure" col="col-md-6" placeholder="e.g. Vicryl 1 to sheath, skin staples" />
                        <x-form.input name="complications" label="Complications" col="col-md-6" placeholder="None" />
                        <x-form.textarea name="postop_orders" label="Post-operative orders" col="col-12" rows="3" required placeholder="Observations, analgesia, antibiotics, fluids, feeding, mobilisation, review" />
                        <div class="col-12 text-end">
                            <button class="btn btn-success px-4" onclick="return confirm('Sign the operation note and complete the case?')"><i class="bi bi-patch-check me-1"></i> Sign & complete</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted small mb-0">Written by the surgeon once the patient is in theatre.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Team & timings</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Surgeon</dt><dd class="col-7">{{ $surgery->surgeon?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Assistant</dt><dd class="col-7">{{ $surgery->assistant ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Anaesthetist</dt><dd class="col-7">{{ $surgery->anaesthetist?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">In theatre</dt><dd class="col-7">{{ $surgery->in_theatre_at?->format('H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Incision</dt><dd class="col-7">{{ $surgery->incision_at?->format('H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Out</dt><dd class="col-7">{{ $surgery->out_at?->format('H:i') ?? '—' }}{{ $surgery->durationMinutes() ? ' ('.$surgery->durationMinutes().' min)' : '' }}</dd>
                </dl>
                <hr>
                <div class="text-muted">Indication</div>
                <div style="white-space: pre-line;">{{ $surgery->indication }}</div>
                @if ($surgery->admission)
                    <div class="mt-2"><a href="{{ route('inpatients.show', $surgery->admission) }}">Inpatient: {{ $surgery->admission->ward->name }}</a></div>
                @endif
                @if ($surgery->pregnancy)
                    <div class="mt-1"><a href="{{ route('maternity.show', $surgery->pregnancy) }}">Pregnancy record</a></div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header">Latest vitals</div>
            <div class="card-body">
                @if ($latestVitals)
                    <div class="small text-muted mb-2">{{ format_date($latestVitals->recorded_at, true) }}</div>
                    @include('vitals._summary', ['v' => $latestVitals])
                @else
                    <span class="text-muted small">None recorded.</span>
                @endif
            </div>
        </div>
    </div>
</div>

@can('theatre.book')
    <div class="modal fade" id="stopModal" tabindex="-1" aria-labelledby="stopLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('theatre.stop', $surgery) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="stopLabel">Postpone or cancel</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="status" id="st-p" value="postponed" checked><label class="btn btn-outline-secondary" for="st-p">Postpone</label>
                        <input type="radio" class="btn-check" name="status" id="st-c" value="cancelled"><label class="btn btn-outline-danger" for="st-c">Cancel</label>
                    </div>
                    <label for="reason" class="form-label">Reason</label>
                    <select id="reason" name="reason" class="form-select" required>
                        @foreach (['Patient unfit for surgery', 'Patient not fasted', 'No consent', 'Theatre time overrun', 'Surgeon unavailable', 'Equipment / supplies unavailable', 'Blood unavailable', 'Patient declined', 'Emergency took priority', 'Booked in error'] as $r)
                            <option>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Confirm</button></div>
            </form>
        </div>
    </div>
@endcan
@endsection
