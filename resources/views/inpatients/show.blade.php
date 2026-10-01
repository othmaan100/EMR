@extends('layouts.app')

@section('title', 'Inpatient — '.$admission->admission_number)

@section('content')
@php
    $user = auth()->user();
    $current = $admission->isCurrent();
    $canNote = $current && $user->can('admissions.notes');
    $canRx = $current && $user->can('prescriptions.create');
    $canMar = $current && $user->can('medication.administer');
    $canLab = $current && $user->can('lab.request');
    $canImg = $current && $user->can('imaging.request');
@endphp

@include('patients._mini-banner')

<div @class(['alert d-flex flex-wrap justify-content-between align-items-center gap-2 py-2', 'alert-primary' => $current, 'alert-secondary' => ! $current])>
    <span>
        <i class="bi bi-hospital me-1"></i>
        <strong>{{ $admission->admission_number }}</strong> ·
        {{ $admission->ward->name }}, bed <strong>{{ $admission->bed?->label ?? '—' }}</strong> ·
        Day {{ $admission->lengthOfStay() }} · Admitted {{ format_date($admission->admitted_at, true) }}
        @if ($admission->doctor) · {{ $admission->doctor->name }}@endif
        @unless ($current)
            · <strong>{{ \App\Models\Admission::DISCHARGE_TYPES[$admission->discharge_type] ?? 'Discharged' }}</strong> {{ format_date($admission->discharged_at, true) }}
        @endunless
    </span>
    <span class="d-flex gap-2">
        @can('vitals.record')
            @if ($current)<a href="{{ route('vitals.create', $patient) }}" class="btn btn-sm btn-light"><i class="bi bi-heart-pulse me-1"></i> Record vitals</a>@endif
        @endcan
        @can('theatre.book')
            @if ($current)<a href="{{ route('theatre.create', $patient) }}" class="btn btn-sm btn-light"><i class="bi bi-scissors me-1"></i> Book surgery</a>@endif
        @endcan
        @if ($current)
            @can('admissions.discharge')
                <a href="{{ route('inpatients.discharge', $admission) }}" class="btn btn-sm btn-warning"><i class="bi bi-box-arrow-right me-1"></i> Discharge</a>
            @endcan
        @else
            <a href="{{ route('inpatients.summary', $admission) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i> Discharge summary</a>
        @endif
    </span>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        {{-- ============ Notes ============ --}}
        <div class="card mb-3" id="notes">
            <div class="card-header"><i class="bi bi-journal-text me-1"></i> Ward round & progress notes</div>
            @if ($canNote)
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('inpatients.notes', $admission) }}">
                        @csrf
                        <div class="d-flex gap-2 mb-2">
                            @foreach (\App\Models\AdmissionNote::TYPES as $key => $t)
                                <input type="radio" class="btn-check" name="type" id="nt-{{ $key }}" value="{{ $key }}"
                                       @checked(old('type', $user->hasRole('Nurse') ? 'nursing' : 'ward_round') === $key)>
                                <label class="btn btn-sm btn-outline-{{ $t['color'] }}" for="nt-{{ $key }}">{{ $t['label'] }}</label>
                            @endforeach
                        </div>
                        <textarea name="note" rows="3" required maxlength="5000" aria-label="Note"
                                  @class(['form-control mb-2', 'is-invalid' => $errors->note->has('note')]) placeholder="S/O/A/P, plan, observations…">{{ old('note') }}</textarea>
                        <div class="text-end"><button class="btn btn-sm btn-primary">Add note</button></div>
                    </form>
                </div>
            @endif
            <ul class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                @forelse ($admission->notes as $note)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span><span class="badge text-bg-{{ \App\Models\AdmissionNote::TYPES[$note->type]['color'] }}">{{ \App\Models\AdmissionNote::TYPES[$note->type]['label'] }}</span> {{ $note->author?->name }}</span>
                            <span>{{ format_date($note->created_at, true) }}</span>
                        </div>
                        <div style="white-space: pre-line;">{{ $note->note }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No notes yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- ============ Drug chart ============ --}}
        <div class="card mb-3" id="medications">
            <div class="card-header"><i class="bi bi-capsule me-1"></i> Drug chart</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Medication</th><th>Directions</th><th>Pharmacy</th><th>Last given</th><th style="min-width: 16rem;"></th></tr></thead>
                    <tbody>
                        @forelse ($medications as $item)
                            <tr @class(['text-muted' => $item->stopped_at])>
                                <td class="ps-3">
                                    <span @class(['fw-semibold', 'text-decoration-line-through' => $item->stopped_at])>{{ $item->drug_name }}</span>
                                    @if ($item->stopped_at)<small class="d-block">Stopped {{ format_date($item->stopped_at, true) }}</small>@endif
                                    @if ($item->allergy_override)<span class="badge text-bg-danger">allergy override</span>@endif
                                </td>
                                <td class="small">{{ $item->directions() }}@if ($item->instructions)<span class="d-block text-muted">{{ $item->instructions }}</span>@endif</td>
                                <td class="small">
                                    @if ($item->quantity_dispensed)<span class="text-success">Given out: {{ $item->quantity_dispensed }}</span>
                                    @elseif ($item->not_dispensed_reason)<span class="text-danger">{{ $item->not_dispensed_reason }}</span>
                                    @else<span class="text-warning-emphasis">Awaiting pharmacy</span>@endif
                                </td>
                                <td class="small">{{ isset($lastDose[$item->id]) ? \Illuminate\Support\Carbon::parse($lastDose[$item->id])->format('d M h:i A') : '—' }}</td>
                                <td class="pe-3">
                                    @unless ($item->stopped_at)
                                        @if ($canMar)
                                            <form method="POST" action="{{ route('inpatients.administer', $admission) }}" class="d-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="prescription_item_id" value="{{ $item->id }}">
                                                <select name="status" class="form-select form-select-sm" style="width: 7rem;" aria-label="Dose status">
                                                    @foreach (\App\Models\MedicationAdministration::STATUSES as $k => $st)<option value="{{ $k }}">{{ $st['label'] }}</option>@endforeach
                                                </select>
                                                <input type="text" name="note" maxlength="255" class="form-control form-control-sm" placeholder="Note / reason" aria-label="Note">
                                                <button class="btn btn-sm btn-success" title="Record dose"><i class="bi bi-check2"></i></button>
                                            </form>
                                        @endif
                                        @if ($canRx)
                                            <form method="POST" action="{{ route('inpatients.stop-medication', $item) }}" class="mt-1" onsubmit="return confirm('Stop {{ addslashes($item->drug_name) }}?')">
                                                @csrf
                                                <button class="btn btn-link btn-sm text-danger p-0">Stop</button>
                                            </form>
                                        @endif
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No medication charted.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('note', 'mar')<div class="card-body py-2 text-danger small">{{ $message }}</div>@enderror

            @if ($canRx)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('inpatients.prescribe', $admission) }}" class="row g-2 align-items-end">
                        @csrf
                        @if ($errors->rx->any())
                            <div class="col-12">
                                <div class="alert alert-danger py-2 small mb-0">
                                    {{ $errors->rx->first() }}
                                    @if (str_starts_with($errors->rx->first('drug'), 'ALLERGY'))
                                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="allergy_override" value="1" id="allergy_override">
                                            <label class="form-check-label fw-semibold" for="allergy_override">Prescribe anyway</label></div>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="col-md-4">
                            <label for="rx_drug" class="form-label small mb-1">Add medication</label>
                            <input type="text" id="rx_drug" name="drug" list="drug-list" required value="{{ old('drug') }}" class="form-control form-control-sm" autocomplete="off">
                            <datalist id="drug-list">@foreach ($drugs as $d)<option value="{{ $d->label }}"></option>@endforeach</datalist>
                        </div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="rx_dose">Dose</label><input type="text" id="rx_dose" name="dose" required maxlength="50" value="{{ old('dose') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1" for="rx_route">Route</label>
                            <select id="rx_route" name="route" class="form-select form-select-sm">@foreach (\App\Models\Prescription::ROUTES as $r)<option @selected(old('route', 'Oral') === $r)>{{ $r }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1" for="rx_freq">Frequency</label>
                            <select id="rx_freq" name="frequency" class="form-select form-select-sm">@foreach (\App\Models\Prescription::FREQUENCIES as $c => $l)<option value="{{ $c }}" @selected(old('frequency', 'TDS') === $c)>{{ $c }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1" for="rx_dur">Days</label>
                            <input type="number" id="rx_dur" name="duration_value" min="1" max="365" value="{{ old('duration_value', 5) }}" class="form-control form-control-sm">
                            <input type="hidden" name="duration_unit" value="days">
                        </div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="rx_qty">Qty</label><input type="number" id="rx_qty" name="quantity" min="1" value="{{ old('quantity') }}" class="form-control form-control-sm" placeholder="opt."></div>
                        <div class="col-md-8"><input type="text" name="instructions" maxlength="255" value="{{ old('instructions') }}" class="form-control form-control-sm" placeholder="Instructions (optional)" aria-label="Instructions"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Chart & send</button></div>
                    </form>
                </div>
            @endif

            @if ($doses->isNotEmpty())
                <div class="card-footer bg-white">
                    <div class="small fw-semibold text-muted mb-1">Recent doses</div>
                    @foreach ($doses->take(8) as $dose)
                        <div class="small">
                            <span class="badge text-bg-{{ \App\Models\MedicationAdministration::STATUSES[$dose->status]['color'] }}">{{ \App\Models\MedicationAdministration::STATUSES[$dose->status]['label'] }}</span>
                            {{ $dose->administered_at->format('d M h:i A') }} · {{ $dose->item->drug_name }} · {{ $dose->user?->name }}
                            @if ($dose->note)<span class="text-muted">— {{ $dose->note }}</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ Investigations ============ --}}
        <div class="card mb-3" id="orders">
            <div class="card-header"><i class="bi bi-clipboard2-data me-1"></i> Investigations</div>
            <div class="card-body">
                @forelse ($admission->labOrders as $order)
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <span><strong class="small">{{ $order->order_number }}</strong> <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                                {{ $order->items->map(fn ($i) => $i->test->name)->implode(', ') }}</span>
                            <span class="text-nowrap">
                                @if ($order->isReleased() && $user->can('lab.results.view'))
                                    <a href="{{ route('lab.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Report</a>
                                @elseif ($order->status === 'requested' && $canLab)
                                    <form method="POST" action="{{ route('inpatients.cancel-order', ['lab', $order->id]) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-link btn-sm text-danger p-0">Cancel</button></form>
                                @endif
                            </span>
                        </div>
                        @if ($order->isReleased() && $user->can('lab.results.view'))
                            <div class="mt-2">@include('laboratory._results')</div>
                        @endif
                    </div>
                @empty
                @endforelse
                @foreach ($admission->imagingOrders as $order)
                    <div class="border rounded p-2 mb-2 d-flex justify-content-between">
                        <span><strong class="small">{{ $order->order_number }}</strong> <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span> {{ $order->test->name }}
                            @if ($order->isReleased() && $user->can('imaging.results.view'))<span class="d-block small"><strong>Impression:</strong> {{ $order->impression }}</span>@endif
                        </span>
                        @if ($order->isReleased() && $user->can('imaging.results.view'))
                            <a href="{{ route('radiology.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary align-self-start">Report</a>
                        @elseif ($order->status === 'requested' && $canImg)
                            <form method="POST" action="{{ route('inpatients.cancel-order', ['imaging', $order->id]) }}">@csrf @method('PATCH')<button class="btn btn-link btn-sm text-danger p-0">Cancel</button></form>
                        @endif
                    </div>
                @endforeach
                @if ($admission->labOrders->isEmpty() && $admission->imagingOrders->isEmpty())<p class="text-muted small">No investigations ordered on the ward.</p>@endif

                @if ($canLab)
                    <details class="mt-2" @if ($errors->lab->any()) open @endif>
                        <summary class="small fw-semibold">Request lab tests</summary>
                        <form method="POST" action="{{ route('inpatients.lab', $admission) }}" class="border rounded p-2 mt-2 bg-light">
                            @csrf
                            @if ($errors->lab->any())<div class="text-danger small">{{ $errors->lab->first() }}</div>@endif
                            <input type="search" class="form-control form-control-sm mb-2" placeholder="Filter tests…" data-filter-list="#ward-lab-list" aria-label="Filter tests">
                            <div id="ward-lab-list" class="row g-1" style="max-height: 220px; overflow-y: auto;">
                                @foreach ($labTests as $category => $tests)
                                    <div class="col-md-6" data-filter-group>
                                        <div class="small fw-semibold text-muted text-uppercase">{{ $category }}</div>
                                        @foreach ($tests as $test)
                                            <div class="form-check" data-filter-item>
                                                <input class="form-check-input" type="checkbox" name="tests[]" value="{{ $test->id }}" id="wt-{{ $test->id }}">
                                                <label class="form-check-label small" for="wt-{{ $test->id }}">{{ $test->name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <select name="priority" class="form-select form-select-sm" style="width: 8rem;" aria-label="Priority"><option value="routine">Routine</option><option value="urgent">Urgent</option></select>
                                <input type="text" name="clinical_notes" maxlength="1000" class="form-control form-control-sm" placeholder="Clinical notes" aria-label="Clinical notes">
                                <button class="btn btn-sm btn-outline-primary text-nowrap">Request</button>
                            </div>
                        </form>
                    </details>
                @endif
                @if ($canImg)
                    <details class="mt-2" @if ($errors->imaging->any()) open @endif>
                        <summary class="small fw-semibold">Request imaging</summary>
                        <form method="POST" action="{{ route('inpatients.imaging', $admission) }}" class="border rounded p-2 mt-2 bg-light d-flex flex-wrap gap-2">
                            @csrf
                            @if ($errors->imaging->any())<div class="text-danger small w-100">{{ $errors->imaging->first() }}</div>@endif
                            <select name="imaging_test_id" class="form-select form-select-sm" style="max-width: 16rem;" required aria-label="Examination">
                                <option value="">Examination…</option>
                                @foreach ($imagingTests as $modality => $tests)
                                    <optgroup label="{{ $modality }}">@foreach ($tests as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</optgroup>
                                @endforeach
                            </select>
                            <select name="priority" class="form-select form-select-sm" style="width: 8rem;" aria-label="Priority"><option value="routine">Routine</option><option value="urgent">Urgent</option></select>
                            <input type="text" name="clinical_notes" required maxlength="1000" class="form-control form-control-sm" style="max-width: 20rem;" placeholder="Clinical indication" aria-label="Clinical indication">
                            <button class="btn btn-sm btn-outline-primary">Request</button>
                        </form>
                    </details>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Admission</div>
            <div class="card-body small">
                <div class="text-muted">Reason / working diagnosis</div>
                <div class="mb-2" style="white-space: pre-line;">{{ $admission->reason }}</div>
                <div class="text-muted">Admitted by {{ $admission->admittedBy?->name }}</div>
                @unless ($current)
                    <hr>
                    <div class="text-muted">Final diagnosis</div><div class="mb-2">{{ $admission->final_diagnosis }}</div>
                    <div class="text-muted">Discharged by {{ $admission->dischargedBy?->name }}</div>
                @endunless
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-heart-pulse me-1"></i> Latest vitals</div>
            <div class="card-body">
                @if ($latestVitals)
                    <div class="small text-muted mb-2">{{ format_date($latestVitals->recorded_at, true) }}</div>
                    @include('vitals._summary', ['v' => $latestVitals])
                @else
                    <span class="text-muted small">None recorded.</span>
                @endif
                @can('vitals.view')<a href="{{ route('vitals.index', $patient) }}" class="small d-block mt-2">Vitals history & charts</a>@endcan
            </div>
        </div>

        @can('admissions.manage')
            @if ($current)
                <div class="card mb-3">
                    <div class="card-header">Transfer</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('inpatients.transfer', $admission) }}">
                            @csrf
                            <select name="bed_id" class="form-select form-select-sm mb-2" required aria-label="New bed">
                                <option value="">Move to bed…</option>
                                @foreach ($freeBeds as $w)
                                    @continue($w->beds->isEmpty())
                                    <optgroup label="{{ $w->name }}">@foreach ($w->beds as $b)<option value="{{ $b->id }}">{{ $w->code }} · {{ $b->label }}</option>@endforeach</optgroup>
                                @endforeach
                            </select>
                            <input type="text" name="reason" required maxlength="255" class="form-control form-control-sm mb-2" placeholder="Reason" aria-label="Reason">
                            @error('bed_id')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                            <button class="btn btn-sm btn-outline-primary w-100">Transfer</button>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @can('billing.view')
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Billing</span>
                    <a href="{{ route('billing.account', $patient) }}" class="small fw-normal">Account</a>
                </div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>Admission charges</span><strong>{{ money($billTotals['amount'] ?? 0) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Insurance share</span><span>{{ money($billTotals['insurance'] ?? 0) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Deposit available</span><span class="text-success">{{ money($deposit) }}</span></div>
                    <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>Patient owes (all bills)</span><strong class="{{ $outstanding > 0 ? 'text-danger' : '' }}">{{ money($outstanding) }}</strong></div>
                    <div class="text-muted mt-1">Bed charged to {{ $admission->bed_charged_until ? format_date($admission->bed_charged_until) : '—' }}</div>
                    @if ($current)
                        @can('admissions.manage')
                            <form method="POST" action="{{ route('inpatients.charge-beds', $admission) }}" class="mt-2">@csrf<button class="btn btn-sm btn-light w-100">Update bed charges</button></form>
                        @endcan
                    @endif
                </div>
            </div>
        @endcan

        <div class="card">
            <div class="card-header">Bed history</div>
            <ul class="list-group list-group-flush small">
                @foreach ($admission->movements as $m)
                    <li class="list-group-item">
                        {{ format_date($m->created_at, true) }} ·
                        @if ($m->fromBed){{ $m->fromBed->ward->code }} {{ $m->fromBed->label }} → @endif
                        {{ $m->toBed?->ward?->code }} {{ $m->toBed?->label }}
                        <span class="d-block text-muted">{{ $m->reason }} · {{ $m->user?->name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
