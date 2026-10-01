@extends('layouts.app')

@section('title', $patient->hospital_number.' — '.$patient->full_name)

@section('content')
@if ($currentAdmission)
    <div class="alert alert-primary d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-hospital me-1"></i> Inpatient in <strong>{{ $currentAdmission->ward->name }}</strong>, bed <strong>{{ $currentAdmission->bed?->label }}</strong> — day {{ $currentAdmission->lengthOfStay() }}</span>
        @can('admissions.view')
            <a href="{{ route('inpatients.show', $currentAdmission) }}" class="btn btn-sm btn-primary">Open inpatient chart</a>
        @endcan
    </div>
@endif

@if ($openVisit)
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            <i class="bi bi-hospital me-1"></i> Currently in <strong>{{ $openVisit->clinic->name }}</strong> —
            queue <strong>{{ $openVisit->queue_number }}</strong>,
            <span class="badge text-bg-{{ $openVisit->statusColor() }}">{{ $openVisit->statusLabel() }}</span>
            since {{ $openVisit->checked_in_at->format('h:i A') }}
        </span>
        @can('queue.view')
            <a href="{{ route('queue.index', ['clinic_id' => $openVisit->clinic_id]) }}" class="btn btn-sm btn-outline-primary">Open queue</a>
        @endcan
    </div>
@endif

{{-- Patient banner --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            @if ($patient->photo)
                <img src="{{ route('patients.photo', $patient) }}" alt="Photo" class="rounded border" style="width:88px;height:88px;object-fit:cover">
            @else
                <span class="avatar bg-secondary" style="width:88px;height:88px;font-size:1.8rem;border-radius:8px">{{ $patient->initials }}</span>
            @endif

            <div class="flex-grow-1">
                <h2 class="h4 mb-1">
                    {{ $patient->full_name }}
                    @if ($patient->is_deceased)<span class="badge text-bg-dark fs-6 align-middle">Deceased {{ format_date($patient->date_of_death) }}</span>@endif
                </h2>
                <div class="d-flex flex-wrap gap-3 text-muted">
                    <span><i class="bi bi-upc me-1"></i><strong class="text-brand">{{ $patient->hospital_number }}</strong></span>
                    <span><i class="bi bi-gender-ambiguous me-1"></i>{{ ucfirst($patient->gender) }}</span>
                    <span><i class="bi bi-cake2 me-1"></i>{{ $patient->age ?? 'Age unknown' }}{{ $patient->dob_estimated ? ' (est.)' : '' }}</span>
                    @if ($patient->phone)<span><i class="bi bi-telephone me-1"></i>{{ $patient->phone }}</span>@endif
                    <span><i class="bi bi-wallet2 me-1"></i>{{ $patient->paymentLabel() }}</span>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    @if ($patient->blood_group)<span class="badge text-bg-danger">Blood {{ $patient->blood_group }}</span>@endif
                    @if ($patient->genotype)<span class="badge text-bg-secondary">Genotype {{ $patient->genotype }}</span>@endif
                    @if ($patient->allergies)
                        <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Allergies: {{ Str::limit($patient->allergies, 60) }}</span>
                    @else
                        <span class="badge text-bg-light border">No known allergies</span>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-self-start">
                @if (! $openVisit && ! $patient->is_deceased)
                    @can('visits.checkin')
                        <a href="{{ route('visits.create', $patient) }}" class="btn btn-success btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Check in</a>
                    @endcan
                @endif
                @can('appointments.manage')
                    @unless ($patient->is_deceased)
                        <a href="{{ route('appointments.create', ['patient_id' => $patient->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i> Book</a>
                    @endunless
                @endcan
                @can('theatre.book')
                    @unless ($patient->is_deceased)
                        <a href="{{ route('theatre.create', $patient) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-scissors me-1"></i> Book surgery</a>
                    @endunless
                @endcan
                @can('maternity.record')
                    @if ($patient->gender === 'female' && ! $patient->is_deceased && ! $pregnancies->firstWhere('status', 'active'))
                        <a href="{{ route('maternity.create', $patient) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-heart me-1"></i> Book ANC</a>
                    @endif
                @endcan
                @can('admissions.manage')
                    @if (! $currentAdmission && ! $patient->is_deceased)
                        <a href="{{ route('inpatients.create', ['patient' => $patient, 'visit' => $openVisit?->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-hospital me-1"></i> Admit</a>
                    @endif
                @endcan
                <a href="{{ route('patients.card', $patient) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Print card</a>
                @can('patients.update')
                    <a href="{{ route('patients.edit', $patient) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
                @endcan
                @can('patients.delete')
                    <form method="POST" action="{{ route('patients.destroy', $patient) }}" onsubmit="return confirm('Archive this patient record? It will be hidden from searches.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-archive me-1"></i> Archive</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Demographics</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Date of birth</dt>
                    <dd class="col-sm-8">{{ $patient->date_of_birth ? format_date($patient->date_of_birth) : '—' }}{{ $patient->dob_estimated ? ' (estimated)' : '' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Marital status</dt><dd class="col-sm-8">{{ $patient->marital_status ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">National ID</dt><dd class="col-sm-8">{{ $patient->national_id ?: '—' }} @if ($patient->nin_verified_at)<span class="badge text-bg-success" title="Verified {{ format_date($patient->nin_verified_at) }}">verified</span>@endif</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Occupation</dt><dd class="col-sm-8">{{ $patient->occupation ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Religion</dt><dd class="col-sm-8">{{ $patient->religion ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Nationality</dt><dd class="col-sm-8">{{ $patient->nationality ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Address</dt>
                    <dd class="col-sm-8">{{ collect([$patient->address, $patient->city, $patient->state, $patient->country])->filter()->implode(', ') ?: '—' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Other contacts</dt>
                    <dd class="col-sm-8">{{ collect([$patient->alt_phone, $patient->email])->filter()->implode(' · ') ?: '—' }}</dd>
                    @if ($patient->legacy_number)
                        <dt class="col-sm-4 text-muted fw-normal">Old folder number</dt><dd class="col-sm-8">{{ $patient->legacy_number }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @can('vitals.view')
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Latest vital signs
                        @if ($latestVitals)<small class="text-muted fw-normal">— {{ format_date($latestVitals->recorded_at, true) }}, {{ $latestVitals->recorder?->name }}</small>@endif
                    </span>
                    <span class="d-flex gap-2">
                        @can('vitals.record')
                            <a href="{{ route('vitals.create', $patient) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Record</a>
                        @endcan
                        <a href="{{ route('vitals.index', $patient) }}" class="btn btn-sm btn-light">History & charts</a>
                    </span>
                </div>
                <div class="card-body">
                    @if ($latestVitals)
                        @include('vitals._summary', ['v' => $latestVitals])
                    @else
                        <span class="text-muted">No vital signs recorded yet.</span>
                    @endif
                </div>
                @if ($notes->isNotEmpty())
                    <ul class="list-group list-group-flush border-top">
                        @foreach ($notes as $note)
                            <li class="list-group-item small">
                                <span class="text-muted">{{ format_date($note->created_at, true) }} · {{ $note->author?->name }} ·</span>
                                <span class="badge text-bg-light border">{{ \App\Models\NursingNote::TYPES[$note->type] ?? $note->type }}</span>
                                {{ Str::limit($note->note, 160) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endcan

        @if ($upcoming->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Upcoming appointments</div>
                <ul class="list-group list-group-flush">
                    @foreach ($upcoming as $appt)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong>{{ format_date($appt->scheduled_at) }}</strong> {{ $appt->scheduled_at->format('h:i A') }}
                                — {{ $appt->clinic->name }}{{ $appt->doctor ? ' with '.$appt->doctor->name : '' }}
                                @if ($appt->reason)<small class="d-block text-muted">{{ $appt->reason }}</small>@endif
                            </span>
                            <span class="badge text-bg-primary">{{ \App\Models\Appointment::TYPES[$appt->type] ?? $appt->type }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('lab.results.view')
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-droplet-half me-1"></i> Lab results</div>
                <ul class="list-group list-group-flush">
                    @forelse ($labOrders as $order)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span>
                                <span class="small text-muted">{{ format_date($order->created_at) }} · {{ $order->order_number }}</span>
                                <span class="d-block">{{ $order->items->map(fn ($i) => $i->test->name)->implode(', ') }}</span>
                            </span>
                            <span class="text-end text-nowrap">
                                @if ($order->isReleased())
                                    @if ($order->abnormalCount())<span class="badge text-bg-danger"><i class="bi bi-exclamation-circle"></i> {{ $order->abnormalCount() }} abnormal</span>@endif
                                    <a href="{{ route('lab.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                                @else
                                    <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item small text-muted">No lab tests yet.</li>
                    @endforelse
                </ul>
            </div>
        @endcan

        @can('imaging.results.view')
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-radioactive me-1"></i> Imaging</div>
                <ul class="list-group list-group-flush">
                    @forelse ($imagingOrders as $order)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span>
                                <span class="small text-muted">{{ format_date($order->created_at) }} · {{ $order->order_number }}</span>
                                <span class="d-block">{{ $order->test->name }}</span>
                                @if ($order->isReleased())<span class="small d-block">{{ Str::limit($order->impression, 140) }}</span>@endif
                            </span>
                            <span class="text-nowrap">
                                @if ($order->isReleased())
                                    <a href="{{ route('radiology.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                                @else
                                    <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item small text-muted">No imaging yet.</li>
                    @endforelse
                </ul>
            </div>
        @endcan

        <div class="card mb-3">
            <div class="card-header">Visit history</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Visit no.</th><th>Clinic</th><th>Doctor</th><th>Diagnosis / reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($visits as $visit)
                            <tr>
                                <td class="ps-3 text-nowrap">{{ format_date($visit->checked_in_at) }}</td>
                                <td class="small">{{ $visit->visit_number }}</td>
                                <td>{{ $visit->clinic->name }}</td>
                                <td class="small">{{ $visit->doctor?->name ?? '—' }}</td>
                                <td class="small">
                                    @if ($visit->consultation?->diagnoses->isNotEmpty())
                                        {{ $visit->consultation->diagnoses->first()->description }}
                                        @if ($visit->consultation->diagnoses->count() > 1)<span class="text-muted">+{{ $visit->consultation->diagnoses->count() - 1 }}</span>@endif
                                    @else
                                        {{ Str::limit($visit->complaint, 40) ?: '—' }}
                                    @endif
                                </td>
                                <td><span class="badge text-bg-{{ $visit->statusColor() }}">{{ $visit->statusLabel() }}</span></td>
                                <td class="text-end pe-2">
                                    @if ($visit->consultation)
                                        @can('consultations.view')
                                            <a href="{{ route('consultations.show', $visit) }}" class="btn btn-sm btn-light" title="Open consultation"><i class="bi bi-journal-text"></i></a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted small py-3">No visits yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can('admissions.view')
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-hospital me-1"></i> Admissions</div>
                <ul class="list-group list-group-flush">
                    @forelse ($admissions as $a)
                        <a href="{{ route('inpatients.show', $a) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>
                                <span class="small text-muted">{{ format_date($a->admitted_at) }}{{ $a->discharged_at ? ' – '.format_date($a->discharged_at) : '' }} · {{ $a->admission_number }}</span>
                                <span class="d-block">{{ $a->ward->name }} · {{ Str::limit($a->final_diagnosis ?? $a->reason, 80) }}</span>
                            </span>
                            @if ($a->isCurrent())
                                <span class="badge text-bg-primary">Admitted · day {{ $a->lengthOfStay() }}</span>
                            @else
                                <span class="badge text-bg-light border">{{ \App\Models\Admission::DISCHARGE_TYPES[$a->discharge_type] ?? 'Discharged' }}</span>
                            @endif
                        </a>
                    @empty
                        <li class="list-group-item small text-muted">No admissions.</li>
                    @endforelse
                </ul>
            </div>
        @endcan
    </div>

    <div class="col-lg-4">
        @can('billing.view')
            @php
                $balance = $patient->outstandingBalance();
            @endphp
            <div class="card mb-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <span>
                        <span class="small text-muted d-block">Account balance</span>
                        <span class="h5 mb-0 {{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</span>
                    </span>
                    <a href="{{ route('billing.account', $patient) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-coin me-1"></i> Account</a>
                </div>
            </div>
        @endcan

        @can('specialty.view')
            @php
                $specialtyCounts = [
                    'dental' => \App\Models\DentalFinding::where('patient_id', $patient->id)->count(),
                    'eye' => \App\Models\EyeExam::where('patient_id', $patient->id)->count(),
                    'physio' => \App\Models\PhysioEpisode::where('patient_id', $patient->id)->count(),
                ];
            @endphp
            <div class="card mb-3">
                <div class="card-header">Specialty clinics</div>
                <div class="list-group list-group-flush">
                    @foreach ([['specialty.dental', 'Dental chart', 'bi-emoji-smile', 'dental'], ['specialty.eye', 'Eye examinations', 'bi-eye', 'eye'],
                               ['specialty.physio', 'Physiotherapy', 'bi-person-walking', 'physio']] as [$route, $label, $icon, $key])
                        <a href="{{ route($route, $patient) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small">
                            <span><i class="bi {{ $icon }} me-2"></i>{{ $label }}</span>
                            @if ($specialtyCounts[$key])<span class="badge text-bg-light border">{{ $specialtyCounts[$key] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endcan

        @if (setting('portal_enabled') && ! $patient->is_deceased)
            @can('portal.manage')
                @php
                    $portalAccount = \App\Models\PatientAccount::where('patient_id', $patient->id)->first();
                @endphp
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-globe me-1"></i> Patient portal</div>
                    <div class="card-body small">
                        @if (! $portalAccount)
                            <p class="text-muted mb-2">No portal access yet.</p>
                        @elseif (! $portalAccount->is_active)
                            <p class="mb-2"><span class="badge text-bg-secondary">Switched off</span></p>
                        @elseif ($portalAccount->isActivated())
                            <p class="mb-2"><span class="badge text-bg-success">Active</span>
                                @if ($portalAccount->last_login_at)<span class="text-muted">· last signed in {{ $portalAccount->last_login_at->diffForHumans() }}</span>@endif
                                @if ($portalAccount->isLocked())<span class="badge text-bg-danger">Locked</span>@endif
                            </p>
                        @else
                            <p class="mb-2"><span class="badge text-bg-warning">Code issued</span>
                                <span class="text-muted">· {{ $portalAccount->activation_expires_at?->isFuture() ? 'expires '.$portalAccount->activation_expires_at->diffForHumans() : 'expired' }}</span></p>
                        @endif
                        <form method="POST" action="{{ route('patients.portal.store', $patient) }}"
                              @if ($portalAccount?->isActivated()) onsubmit="return confirm('This replaces the patient\'s password with a new activation code. Continue?')" @endif>
                            @csrf
                            @if ($patient->phone)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="send_sms" value="1" id="portal_sms">
                                    <label class="form-check-label" for="portal_sms">Also send the code by SMS to {{ $patient->phone }}</label>
                                </div>
                            @endif
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary">{{ $portalAccount ? 'New activation code' : 'Give portal access' }}</button>
                            </div>
                        </form>
                        @if ($portalAccount?->is_active)
                            <form method="POST" action="{{ route('patients.portal.destroy', $patient) }}" class="mt-2" onsubmit="return confirm('Switch off portal access for this patient?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">Switch off access</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        @endif

        @can('consultations.view')
            <div class="card mb-3">
                <div class="card-header">Confirmed diagnoses</div>
                <ul class="list-group list-group-flush">
                    @forelse ($problems as $dx)
                        <li class="list-group-item small d-flex justify-content-between gap-2">
                            <span>{{ $dx->description }}</span>
                            <span class="text-muted text-nowrap">{{ $dx->icd10_code }} · {{ format_date($dx->created_at) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item small text-muted">None recorded.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header">Recent prescriptions</div>
                <ul class="list-group list-group-flush">
                    @forelse ($prescriptions as $rx)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('prescriptions.print', $rx) }}" target="_blank" class="fw-semibold text-decoration-none">{{ $rx->prescription_number }}</a>
                                <span class="badge text-bg-{{ $rx->statusColor() }}">{{ $rx->statusLabel() }}</span>
                            </div>
                            <div class="text-muted">{{ format_date($rx->created_at) }} · {{ $rx->prescriber?->name }}</div>
                            @foreach ($rx->items as $item)
                                <div>
                                    {{ $item->drug_name }} — {{ $item->directions() }}
                                    @if ($item->quantity_dispensed)<span class="text-success">· given {{ $item->quantity_dispensed }}</span>@endif
                                    @if ($item->not_dispensed_reason)<span class="text-danger">· {{ $item->not_dispensed_reason }}</span>@endif
                                </div>
                            @endforeach
                        </li>
                    @empty
                        <li class="list-group-item small text-muted">None.</li>
                    @endforelse
                </ul>
            </div>
        @endcan

        @if ($surgeries->isNotEmpty())
            @can('theatre.view')
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-scissors me-1"></i> Operations</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($surgeries as $s)
                            <a href="{{ route('theatre.show', $s) }}" class="list-group-item list-group-item-action small d-flex justify-content-between gap-2">
                                <span>{{ format_date($s->scheduled_at) }} · {{ $s->procedure_name }}</span>
                                <span class="badge text-bg-{{ $s->statusColor() }} align-self-start">{{ $s->statusLabel() }}</span>
                            </a>
                        @endforeach
                    </ul>
                </div>
            @endcan
        @endif

        @if ($pregnancies->isNotEmpty())
            @can('maternity.view')
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person-heart me-1"></i> Pregnancies</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($pregnancies as $preg)
                            <a href="{{ route('maternity.show', $preg) }}" class="list-group-item list-group-item-action small">
                                @if ($preg->status === 'active')
                                    <span class="fw-semibold">Current · {{ $preg->gestationLabel() }}</span> · EDD {{ format_date($preg->edd) }}
                                    @if ($preg->labour_started_at)<span class="badge text-bg-warning">In labour</span>@endif
                                    @if (! empty($preg->risk_factors))<span class="badge text-bg-danger">High risk</span>@endif
                                @elseif ($preg->status === 'delivered')
                                    Delivered {{ format_date($preg->delivery?->delivered_at) }} · {{ $preg->delivery?->modeLabel() }}
                                @else
                                    Closed: {{ $preg->end_reason }} ({{ format_date($preg->updated_at) }})
                                @endif
                            </a>
                        @endforeach
                    </ul>
                </div>
            @endcan
        @endif

        @if ($immunizationSummary !== null)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-plus me-1"></i> Immunizations</span>
                    <a href="{{ route('immunizations.show', $patient) }}" class="small fw-normal">Open card</a>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <span class="badge text-bg-success">{{ $immunizationSummary['given'] ?? 0 }} given</span>
                    @if ($immunizationSummary['due'] ?? 0)<span class="badge text-bg-warning"><i class="bi bi-alarm"></i> {{ $immunizationSummary['due'] }} due now</span>@endif
                    @if ($immunizationSummary['overdue'] ?? 0)<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> {{ $immunizationSummary['overdue'] }} overdue</span>@endif
                    <span class="badge text-bg-light border">{{ $immunizationSummary['upcoming'] ?? 0 }} upcoming</span>
                </div>
            </div>
        @endif

        @if ($relatives['mother'] || $relatives['children']->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Family</div>
                <ul class="list-group list-group-flush small">
                    @if ($relatives['mother'])
                        <a href="{{ route('patients.show', $relatives['mother']) }}" class="list-group-item list-group-item-action">Mother: {{ $relatives['mother']->full_name }} · {{ $relatives['mother']->hospital_number }}</a>
                    @endif
                    @foreach ($relatives['children'] as $child)
                        <a href="{{ route('patients.show', $child) }}" class="list-group-item list-group-item-action">Child: {{ $child->full_name }} · {{ $child->age }} · {{ $child->hospital_number }}</a>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">Next of kin</div>
            <div class="card-body">
                @if ($patient->nok_name)
                    <div class="fw-semibold">{{ $patient->nok_name }}</div>
                    <div class="text-muted small mb-1">{{ $patient->nok_relationship }}</div>
                    @if ($patient->nok_phone)<div><i class="bi bi-telephone me-1"></i>{{ $patient->nok_phone }}</div>@endif
                    @if ($patient->nok_address)<div class="small text-muted">{{ $patient->nok_address }}</div>@endif
                @else
                    <span class="text-muted">Not recorded</span>
                @endif
            </div>
        </div>

        @if (in_array($patient->payment_type, ['insurance', 'corporate'], true))
            <div class="card mb-3">
                <div class="card-header">Insurance / Company</div>
                <div class="card-body">
                    <div class="fw-semibold">{{ $patient->insuranceProvider?->name ?? '—' }}</div>
                    <div class="small">Member no: {{ $patient->insurance_number ?: '—' }}</div>
                    @if ($patient->insurance_expiry)
                        <div class="small {{ $patient->insurance_expiry->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                            {{ $patient->insurance_expiry->isPast() ? 'Expired' : 'Expires' }} {{ format_date($patient->insurance_expiry) }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($patient->notes)
            <div class="card mb-3">
                <div class="card-header">Notes</div>
                <div class="card-body small">{!! nl2br(e($patient->notes)) !!}</div>
            </div>
        @endif

        <div class="card">
            <div class="card-body small text-muted">
                Registered {{ format_date($patient->created_at, true) }}
                @if ($patient->registeredBy) by {{ $patient->registeredBy->name }}@endif
                <br>Last updated {{ $patient->updated_at->diffForHumans() }}
                <div class="mt-3 text-center"><svg data-barcode="{{ $patient->hospital_number }}" data-height="36"></svg></div>
            </div>
        </div>
    </div>
</div>
@endsection
