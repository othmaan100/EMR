@extends('layouts.app')

@section('title', 'Pregnancy — '.$patient->hospital_number)

@section('content')
@php
    $m = config('emr.maternity');
    $canRecord = auth()->user()->can('maternity.record');
    $active = $pregnancy->status === 'active';
    $delivery = $pregnancy->delivery;
@endphp

@include('patients._mini-banner')

<div class="row g-3">
    <div class="col-xl-4 order-xl-2">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Pregnancy</span>
                <span class="badge text-bg-{{ ['active' => 'primary', 'delivered' => 'success', 'ended' => 'secondary'][$pregnancy->status] }}">{{ ucfirst($pregnancy->status) }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <div>
                        <div class="small text-muted">Gestation {{ $active ? 'today' : '' }}</div>
                        <div class="h3 mb-0">{{ $active ? $pregnancy->gestationLabel() : ($delivery ? $delivery->gestation_weeks.' weeks at birth' : '—') }}</div>
                    </div>
                    @if ($pregnancy->isHighRisk())<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> High risk</span>@endif
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">EDD</dt><dd class="col-7 fw-semibold">{{ format_date($pregnancy->edd) }} <span class="fw-normal text-muted">{{ $pregnancy->edd_by_scan ? '(scan)' : '(LMP)' }}</span></dd>
                    <dt class="col-5 text-muted fw-normal">LMP</dt><dd class="col-7">{{ $pregnancy->lmp ? format_date($pregnancy->lmp) : '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Obstetric</dt><dd class="col-7">{{ $pregnancy->obstetricFormula() }} · {{ $pregnancy->living_children }} living</dd>
                    <dt class="col-5 text-muted fw-normal">Booked</dt><dd class="col-7">{{ format_date($pregnancy->created_at) }}</dd>
                    @if ($pregnancy->end_reason)<dt class="col-5 text-muted fw-normal">Closed</dt><dd class="col-7">{{ $pregnancy->end_reason }}</dd>@endif
                </dl>
                @if ($pregnancy->riskLabels())
                    <div class="mt-2">@foreach ($pregnancy->riskLabels() as $r)<span class="badge text-bg-warning me-1 mb-1">{{ $r }}</span>@endforeach</div>
                @endif
                @if ($pregnancy->notes)<div class="small mt-2" style="white-space: pre-line;">{{ $pregnancy->notes }}</div>@endif
            </div>
            @if ($active && $canRecord)
                <div class="card-footer bg-white d-grid gap-2">
                    @if ($pregnancy->labour_started_at)
                        <a href="{{ route('maternity.partograph', $pregnancy) }}" class="btn btn-warning"><i class="bi bi-activity me-1"></i> Partograph (in labour)</a>
                    @else
                        <form method="POST" action="{{ route('maternity.labour', $pregnancy) }}" class="d-grid" onsubmit="return confirm('Start labour monitoring (partograph)?')">
                            @csrf<button class="btn btn-outline-warning"><i class="bi bi-activity me-1"></i> Start labour / partograph</button>
                        </form>
                    @endif
                    <a href="{{ route('maternity.delivery', $pregnancy) }}" class="btn btn-success"><i class="bi bi-balloon-heart me-1"></i> Record delivery</a>
                    @can('theatre.book')
                        <a href="{{ route('theatre.create', ['patient' => $patient, 'pregnancy' => $pregnancy->id]) }}" class="btn btn-outline-danger"><i class="bi bi-scissors me-1"></i> Book caesarean section</a>
                    @endcan
                    <button class="btn btn-link btn-sm text-danger" data-bs-toggle="modal" data-bs-target="#endModal">Close record (miscarriage, transfer…)</button>
                </div>
            @elseif ($pregnancy->partograph->isNotEmpty())
                <div class="card-footer bg-white"><a href="{{ route('maternity.partograph', $pregnancy) }}" class="small">View partograph</a></div>
            @endif
        </div>
    </div>

    <div class="col-xl-8 order-xl-1">
        {{-- ================= Delivery ================= --}}
        @if ($delivery)
            <div class="card mb-3" id="delivery">
                <div class="card-header"><i class="bi bi-balloon-heart me-1"></i> Delivery — {{ format_date($delivery->delivered_at, true) }}</div>
                <div class="card-body">
                    <dl class="row small mb-2">
                        <dt class="col-sm-3 text-muted fw-normal">Mode</dt><dd class="col-sm-9 fw-semibold">{{ $delivery->modeLabel() }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Gestation</dt><dd class="col-sm-9">{{ $delivery->gestation_weeks }} weeks</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Blood loss</dt><dd class="col-sm-9">{{ $delivery->blood_loss_ml ? $delivery->blood_loss_ml.' ml' : '—' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Placenta</dt><dd class="col-sm-9">{{ $delivery->placenta_complete ? 'Complete' : 'Incomplete' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Perineum</dt><dd class="col-sm-9">{{ $m['perineum'][$delivery->perineum] ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Complications</dt>
                        <dd class="col-sm-9">{{ collect($delivery->complications)->map(fn ($c) => $m['complications'][$c] ?? $c)->implode(', ') ?: 'None' }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Mother</dt><dd class="col-sm-9 {{ $delivery->maternal_outcome === 'died' ? 'text-danger fw-bold' : '' }}">{{ ucfirst($delivery->maternal_outcome) }}</dd>
                        <dt class="col-sm-3 text-muted fw-normal">Attended by</dt><dd class="col-sm-9">{{ $delivery->attendant?->name }}</dd>
                    </dl>
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Baby</th><th>Sex</th><th>Weight</th><th>Apgar 1/5</th><th>Outcome</th></tr></thead>
                        <tbody>
                            @foreach ($delivery->babies as $b)
                                <tr>
                                    <td>@if ($b->patient)<a href="{{ route('patients.show', $b->patient) }}">{{ $b->patient->hospital_number }}</a>@else — @endif</td>
                                    <td>{{ ucfirst($b->sex) }}</td>
                                    <td @class(['text-danger fw-semibold' => $b->isLowBirthWeight()])>{{ $b->birth_weight_g ? $b->birth_weight_g.' g' : '—' }}{{ $b->isLowBirthWeight() ? ' (LBW)' : '' }}</td>
                                    <td>{{ $b->apgar_1 ?? '—' }} / {{ $b->apgar_5 ?? '—' }}</td>
                                    <td>{{ $b->outcomeLabel() }}{{ $b->resuscitated ? ' · resuscitated' : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3" id="postnatal">
                <div class="card-header"><i class="bi bi-house-heart me-1"></i> Postnatal visits</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light"><tr><th class="ps-3">Date</th><th>Day</th><th>BP / Temp</th><th>Uterus / lochia</th><th>Breastfeeding</th><th>Baby</th><th>FP</th><th>By</th></tr></thead>
                        <tbody>
                            @forelse ($pregnancy->postnatalVisits as $v)
                                <tr>
                                    <td class="ps-3">{{ format_date($v->visit_date) }}</td>
                                    <td>{{ (int) $delivery->delivered_at->copy()->startOfDay()->diffInDays($v->visit_date) }}</td>
                                    <td>{{ $v->systolic ? $v->systolic.'/'.$v->diastolic : '—' }} · {{ $v->temperature ?? '—' }}</td>
                                    <td>{{ $v->uterus ?? '—' }} / {{ $v->lochia ?? '—' }}</td>
                                    <td>{{ $v->breastfeeding ?? '—' }}</td>
                                    <td>{{ $v->baby_weight_g ? $v->baby_weight_g.' g' : '' }} {{ $v->cord ? '· cord '.$v->cord : '' }} {!! $v->jaundice ? '<span class="text-danger">· jaundice</span>' : '' !!}</td>
                                    <td>{{ $v->family_planning ?? '—' }}</td>
                                    <td>{{ $v->recorder?->name }}</td>
                                </tr>
                                @if ($v->mood_concern || $v->notes)
                                    <tr><td colspan="8" class="ps-4 border-top-0">
                                        @if ($v->mood_concern)<span class="badge text-bg-warning">Mood concern</span>@endif {{ $v->notes }}
                                    </td></tr>
                                @endif
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-3">No postnatal visits yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($canRecord)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('maternity.postnatal', $pregnancy) }}" class="row g-2">
                            @csrf
                            @if ($errors->pnc->any())<div class="col-12 text-danger small">{{ $errors->pnc->first() }}</div>@endif
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_date">Date</label><input type="date" id="pnc_date" name="visit_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">BP</label>
                                <div class="input-group input-group-sm"><input type="number" name="systolic" class="form-control" placeholder="Sys" aria-label="Systolic"><input type="number" name="diastolic" class="form-control" placeholder="Dia" aria-label="Diastolic"></div></div>
                            <div class="col-md-2"><label class="form-label small mb-1" for="pnc_temp">Temp °C</label><input type="number" step="0.1" id="pnc_temp" name="temperature" class="form-control form-control-sm"></div>
                            <div class="col-md-2"><label class="form-label small mb-1" for="pnc_uterus">Uterus</label>
                                <select id="pnc_uterus" name="uterus" class="form-select form-select-sm"><option></option><option>Well contracted</option><option>Poorly contracted</option><option>Tender</option></select></div>
                            <div class="col-md-2"><label class="form-label small mb-1" for="pnc_lochia">Lochia</label>
                                <select id="pnc_lochia" name="lochia" class="form-select form-select-sm"><option></option><option>Normal</option><option>Heavy</option><option>Offensive</option></select></div>
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_bf">Breastfeeding</label>
                                <select id="pnc_bf" name="breastfeeding" class="form-select form-select-sm"><option></option><option>Exclusive</option><option>Mixed</option><option>Difficulties</option><option>Not breastfeeding</option></select></div>
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_wound">Wound / perineum</label>
                                <select id="pnc_wound" name="wound" class="form-select form-select-sm"><option></option><option>Healing well</option><option>Infected</option><option>Breakdown</option><option>N/A</option></select></div>
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_fp">Family planning</label><input type="text" id="pnc_fp" name="family_planning" maxlength="255" class="form-control form-control-sm" placeholder="Counselled / method"></div>
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_bw">Baby weight (g)</label><input type="number" id="pnc_bw" name="baby_weight_g" class="form-control form-control-sm"></div>
                            <div class="col-md-3"><label class="form-label small mb-1" for="pnc_cord">Cord</label>
                                <select id="pnc_cord" name="cord" class="form-select form-select-sm"><option></option><option>Clean & dry</option><option>Red / discharge</option><option>Separated</option></select></div>
                            <div class="col-md-5 d-flex gap-3 align-items-end">
                                <div class="form-check"><input type="hidden" name="jaundice" value="0"><input class="form-check-input" type="checkbox" name="jaundice" value="1" id="pnc_j"><label class="form-check-label small" for="pnc_j">Baby jaundiced</label></div>
                                <div class="form-check"><input type="hidden" name="mood_concern" value="0"><input class="form-check-input" type="checkbox" name="mood_concern" value="1" id="pnc_m"><label class="form-check-label small" for="pnc_m">Mood concern</label></div>
                            </div>
                            <div class="col-md-10"><input type="text" name="notes" maxlength="2000" class="form-control form-control-sm" placeholder="Notes" aria-label="Notes"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save visit</button></div>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        {{-- ================= ANC ================= --}}
        <div class="card mb-3" id="anc">
            <div class="card-header"><i class="bi bi-person-heart me-1"></i> Antenatal visits ({{ $pregnancy->ancVisits->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>GA</th><th>BP</th><th>Wt</th><th>FH</th><th>FHR</th><th>Pres.</th><th>Protein</th><th>Hb</th><th>Given</th><th>Next</th></tr></thead>
                    <tbody>
                        @forelse ($pregnancy->ancVisits as $v)
                            @php
                                $v->setRelation('pregnancy', $pregnancy);
                                $alerts = $v->alerts();
                            @endphp
                            <tr @class(['table-danger' => $alerts])>
                                <td class="ps-3 text-nowrap">{{ format_date($v->visit_date) }}</td>
                                <td>{{ $pregnancy->gestationLabel($v->visit_date) }}</td>
                                <td>{{ $v->systolic ? $v->systolic.'/'.$v->diastolic : '—' }}</td>
                                <td>{{ $v->weight ?? '—' }}</td>
                                <td>{{ $v->fundal_height ?? '—' }}</td>
                                <td>{{ $v->fetal_heart_rate ?? '—' }}</td>
                                <td>{{ $v->presentation ?? '—' }}</td>
                                <td>{{ $v->urine_protein ?? '—' }}</td>
                                <td>{{ $v->haemoglobin ?? '—' }}</td>
                                <td>{{ collect($v->interventions)->map(fn ($i) => strtoupper(str_replace('_', '/', $i)))->implode(', ') ?: '—' }}</td>
                                <td class="text-nowrap">{{ $v->next_visit ? format_date($v->next_visit) : '—' }}</td>
                            </tr>
                            @if ($alerts || $v->complaints || $v->notes)
                                <tr @class(['table-danger' => $alerts])>
                                    <td colspan="11" class="ps-4 border-top-0">
                                        @foreach ($alerts as $a)<span class="badge text-bg-danger me-1"><i class="bi bi-exclamation-triangle"></i> {{ $a }}</span>@endforeach
                                        {{ $v->complaints }} {{ $v->notes ? '— '.$v->notes : '' }}
                                        <span class="text-muted">· {{ $v->recorder?->name }}</span>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-3">No visits recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($active && $canRecord)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('maternity.anc', $pregnancy) }}" class="row g-2">
                        @csrf
                        @if ($errors->anc->any())<div class="col-12 text-danger small">{{ $errors->anc->first() }}</div>@endif
                        <div class="col-md-3"><label class="form-label small mb-1" for="anc_date">Date</label><input type="date" id="anc_date" name="visit_date" value="{{ old('visit_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm"></div>
                        <div class="col-md-3"><label class="form-label small mb-1">BP (mmHg)</label>
                            <div class="input-group input-group-sm"><input type="number" name="systolic" value="{{ old('systolic') }}" class="form-control" placeholder="Sys" aria-label="Systolic"><input type="number" name="diastolic" value="{{ old('diastolic') }}" class="form-control" placeholder="Dia" aria-label="Diastolic"></div></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_wt">Weight (kg)</label><input type="number" step="0.1" id="anc_wt" name="weight" value="{{ old('weight') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_fh">Fundal ht (cm)</label><input type="number" id="anc_fh" name="fundal_height" value="{{ old('fundal_height') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_fhr">FHR (/min)</label><input type="number" id="anc_fhr" name="fetal_heart_rate" value="{{ old('fetal_heart_rate') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-3"><label class="form-label small mb-1" for="anc_pres">Presentation</label>
                            <select id="anc_pres" name="presentation" class="form-select form-select-sm"><option value=""></option>@foreach ($m['presentations'] as $p)<option>{{ $p }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_fm">Fetal movement</label>
                            <select id="anc_fm" name="fetal_movement" class="form-select form-select-sm"><option value=""></option><option value="1">Present</option><option value="0">Reduced / absent</option></select></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_prot">Urine protein</label>
                            <select id="anc_prot" name="urine_protein" class="form-select form-select-sm"><option value=""></option>@foreach ($m['urine_protein'] as $p)<option>{{ $p }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label small mb-1" for="anc_oed">Oedema</label>
                            <select id="anc_oed" name="oedema" class="form-select form-select-sm"><option value=""></option>@foreach ($m['oedema'] as $p)<option>{{ $p }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label small mb-1" for="anc_hb">Hb (g/dL)</label><input type="number" step="0.1" id="anc_hb" name="haemoglobin" value="{{ old('haemoglobin') }}" class="form-control form-control-sm"></div>
                        <div class="col-12">
                            <span class="small text-muted me-2">Given today:</span>
                            @foreach ($m['interventions'] as $key => $label)
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="interventions[]" value="{{ $key }}" id="iv-{{ $key }}"><label class="form-check-label small" for="iv-{{ $key }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                        <div class="col-md-5"><input type="text" name="complaints" maxlength="2000" value="{{ old('complaints') }}" class="form-control form-control-sm" placeholder="Complaints" aria-label="Complaints"></div>
                        <div class="col-md-4"><input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}" class="form-control form-control-sm" placeholder="Notes / plan" aria-label="Notes"></div>
                        <div class="col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">Next</span><input type="date" name="next_visit" min="{{ today()->addDay()->toDateString() }}" class="form-control" aria-label="Next visit"></div></div>
                        <div class="col-12 text-end"><button class="btn btn-sm btn-primary px-4">Save ANC visit</button></div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($active && $canRecord)
    <div class="modal fade" id="endModal" tabindex="-1" aria-labelledby="endLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('maternity.end', $pregnancy) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="endLabel">Close pregnancy record</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label for="end_reason" class="form-label">Reason</label>
                    <select id="end_reason" name="end_reason" class="form-select" required>
                        @foreach (['Miscarriage', 'Ectopic pregnancy', 'Termination', 'Transferred to another facility', 'Lost to follow-up', 'Booked in error'] as $r)<option>{{ $r }}</option>@endforeach
                    </select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Close record</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
