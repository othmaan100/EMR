<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\ImagingOrder;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Report definitions. Each report returns:
 *   columns: key => [label, format, total?]   (format: text|int|money|pct|dec1|date; total defaults to true)
 *   rows:    list of arrays keyed by column
 *   chart:   optional {type, title, unit, labels, series:[{label, data}], horizontal?}
 *   tiles:   optional list of [label, value, format]
 *
 * Aggregation is done in PHP where SQL dialects differ (MySQL vs SQLite).
 */
class ReportService
{
    public const REPORTS = [
        // key => [title, group, permission, description, filters]
        'registrations' => ['New registrations', 'Patient flow', 'reports.operational', 'New patients per day, by sex and payment type.', []],
        'visits' => ['Clinic visits & waiting times', 'Patient flow', 'reports.operational', 'Visits per clinic with average wait from check-in to doctor.', ['clinic']],
        'appointments' => ['Appointments & no-shows', 'Patient flow', 'reports.operational', 'Booked appointments by outcome and no-show rate per clinic.', ['clinic']],
        'pharmacy' => ['Drug usage & stock value', 'Patient flow', 'reports.operational', 'Quantities dispensed and current stock value by drug.', []],
        'diagnoses' => ['Top diagnoses', 'Clinical', 'reports.clinical', 'Most frequent diagnoses with age-group and sex breakdown.', ['clinic']],
        'investigations' => ['Lab & imaging workload', 'Clinical', 'reports.clinical', 'Tests and examinations performed, abnormal rate and turnaround.', []],
        'admissions' => ['Admissions & length of stay', 'Clinical', 'reports.clinical', 'Admissions, discharges, outcomes and length of stay per ward.', ['ward']],
        'maternity' => ['Maternity statistics', 'Clinical', 'reports.clinical', 'ANC bookings, deliveries by mode, caesarean rate, birth outcomes and maternal deaths.', []],
        'immunizations' => ['Immunizations given', 'Clinical', 'reports.clinical', 'Doses given per vaccine, and children with overdue doses.', []],
        'specialty' => ['Specialty clinic activity', 'Clinical', 'reports.clinical', 'Dental treatments, eye examinations and physiotherapy sessions.', []],
        'theatre' => ['Theatre activity', 'Clinical', 'reports.clinical', 'Operations by procedure, emergencies, cancellations, durations and surgeon workload.', []],
        'revenue' => ['Revenue by service', 'Finance', 'reports.financial', 'Charges raised, split by payer, grouped by service type.', []],
        'collections' => ['Collections', 'Finance', 'reports.financial', 'Money received per day, method and cashier.', []],
        'outstanding' => ['Outstanding balances', 'Finance', 'reports.financial', 'Patients who owe money, by age of debt.', []],
        'claims' => ['Insurance claims summary', 'Finance', 'reports.financial', 'Insurer shares by claim status per payer, with amounts received and rejected.', []],
        'claimsAgeing' => ['Unpaid claims ageing', 'Finance', 'reports.financial', 'What each insurer owes, by time since the claim was submitted.', []],
        'stores' => ['Store consumption by department', 'Stores', 'reports.operational', 'Value of general-store items issued to each department, and stock on hand.', []],
        'procurement' => ['Purchases & supplier payables', 'Finance', 'reports.financial', 'Goods received per supplier, invoices paid and what is still owed.', []],
        'staff' => ['Staff activity', 'Staff', 'reports.staff', 'Work recorded by each staff member.', []],
    ];

    public function run(string $key, Carbon $from, Carbon $to, array $filters = []): array
    {
        $to = $to->copy()->endOfDay();
        $from = $from->copy()->startOfDay();

        return $this->{'report'.ucfirst($key)}($from, $to, $filters);
    }

    /**
     * Headline numbers for the reports hub.
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $to = $to->copy()->endOfDay();

        return [
            'new_patients' => Patient::whereBetween('created_at', [$from, $to])->count(),
            'visits' => Visit::whereBetween('checked_in_at', [$from, $to])->count(),
            'admissions' => Admission::whereBetween('admitted_at', [$from, $to])->count(),
            'collected' => (float) Payment::whereNull('voided_at')->whereBetween('created_at', [$from, $to])->sum('amount'),
        ];
    }

    // ------------------------------------------------------------------ Patient flow

    protected function reportRegistrations(Carbon $from, Carbon $to, array $f): array
    {
        $patients = Patient::whereBetween('created_at', [$from, $to])->get(['created_at', 'gender', 'payment_type']);
        $byDay = $patients->groupBy(fn ($p) => $p->created_at->toDateString());

        $rows = [];
        foreach ($this->days($from, $to) as $day) {
            $group = $byDay->get($day, collect());
            $rows[] = [
                'date' => $day, 'total' => $group->count(),
                'male' => $group->where('gender', 'male')->count(), 'female' => $group->where('gender', 'female')->count(),
                'insured' => $group->whereIn('payment_type', ['insurance', 'corporate'])->count(),
            ];
        }

        return [
            'columns' => ['date' => ['Date', 'date'], 'total' => ['New patients', 'int'], 'male' => ['Male', 'int'], 'female' => ['Female', 'int'], 'insured' => ['Insured / corporate', 'int']],
            'rows' => $rows,
            'chart' => $this->lineChart('New patients per day', 'patients', $rows, 'date', [['New patients', 'total']]),
            'tiles' => [['Total registered', $patients->count(), 'int'], ['Female', $patients->where('gender', 'female')->count(), 'int'],
                ['Insured / corporate', $patients->whereIn('payment_type', ['insurance', 'corporate'])->count(), 'int']],
        ];
    }

    protected function reportVisits(Carbon $from, Carbon $to, array $f): array
    {
        $visits = Visit::whereBetween('checked_in_at', [$from, $to])
            ->when($f['clinic_id'] ?? null, fn ($q, $v) => $q->where('clinic_id', $v))
            ->get(['clinic_id', 'status', 'checked_in_at', 'consultation_started_at', 'completed_at']);
        $clinics = Clinic::pluck('name', 'id');

        $rows = $visits->groupBy('clinic_id')->map(function (Collection $g, $clinicId) use ($clinics) {
            $waits = $g->filter(fn ($v) => $v->consultation_started_at)->map(fn ($v) => $v->checked_in_at->diffInMinutes($v->consultation_started_at));

            return [
                'clinic' => $clinics[$clinicId] ?? '—',
                'visits' => $g->count(),
                'completed' => $g->where('status', Visit::COMPLETED)->count(),
                'left' => $g->where('status', Visit::LEFT)->count(),
                'avg_wait' => $waits->isEmpty() ? null : round($waits->avg(), 1),
                'max_wait' => $waits->max(),
            ];
        })->sortByDesc('visits')->values()->all();

        $allWaits = $visits->filter(fn ($v) => $v->consultation_started_at)->map(fn ($v) => $v->checked_in_at->diffInMinutes($v->consultation_started_at));

        return [
            'columns' => ['clinic' => ['Clinic', 'text'], 'visits' => ['Visits', 'int'], 'completed' => ['Completed', 'int'],
                'left' => ['Left unseen', 'int'], 'avg_wait' => ['Avg wait to doctor (min)', 'dec1'], 'max_wait' => ['Longest wait (min)', 'int', false]],
            'rows' => $rows,
            'chart' => $this->barChart('Visits by clinic', 'visits', $rows, 'clinic', 'visits'),
            'tiles' => [['Visits', $visits->count(), 'int'], ['Avg wait to doctor (min)', $allWaits->isEmpty() ? null : round($allWaits->avg(), 1), 'dec1'],
                ['Left without being seen', $visits->where('status', Visit::LEFT)->count(), 'int']],
        ];
    }

    protected function reportAppointments(Carbon $from, Carbon $to, array $f): array
    {
        $appts = Appointment::whereBetween('scheduled_at', [$from, $to])
            ->when($f['clinic_id'] ?? null, fn ($q, $v) => $q->where('clinic_id', $v))
            ->get(['clinic_id', 'status', 'scheduled_at']);
        $clinics = Clinic::pluck('name', 'id');

        // Past appointments never checked in count as no-shows.
        $outcome = fn ($a) => $a->status === 'scheduled' && $a->scheduled_at->lt(today()) ? 'no_show' : $a->status;

        $rows = $appts->groupBy('clinic_id')->map(function (Collection $g, $clinicId) use ($clinics, $outcome) {
            $o = $g->map($outcome)->countBy();
            $due = $g->count() - ($o['scheduled'] ?? 0) - ($o['cancelled'] ?? 0);

            return [
                'clinic' => $clinics[$clinicId] ?? '—', 'booked' => $g->count(),
                'attended' => ($o['checked_in'] ?? 0) + ($o['completed'] ?? 0), 'no_show' => $o['no_show'] ?? 0,
                'cancelled' => $o['cancelled'] ?? 0, 'upcoming' => $o['scheduled'] ?? 0,
                'no_show_rate' => $due > 0 ? round(100 * ($o['no_show'] ?? 0) / $due, 1) : null,
            ];
        })->sortByDesc('booked')->values()->all();

        return [
            'columns' => ['clinic' => ['Clinic', 'text'], 'booked' => ['Booked', 'int'], 'attended' => ['Attended', 'int'], 'no_show' => ['No-show', 'int'],
                'cancelled' => ['Cancelled', 'int'], 'upcoming' => ['Upcoming', 'int'], 'no_show_rate' => ['No-show rate', 'pct']],
            'rows' => $rows,
            'chart' => $this->barChart('No-show rate by clinic', '%', array_filter($rows, fn ($r) => $r['no_show_rate'] !== null), 'clinic', 'no_show_rate'),
        ];
    }

    protected function reportPharmacy(Carbon $from, Carbon $to, array $f): array
    {
        $dispensed = StockMovement::where('type', 'dispense')->whereBetween('created_at', [$from, $to])
            ->selectRaw('drug_id, -SUM(quantity) as qty')->groupBy('drug_id')->pluck('qty', 'drug_id');
        $stock = StockBatch::where('quantity_on_hand', '>', 0)->whereDate('expiry_date', '>=', today())
            ->get(['drug_id', 'quantity_on_hand', 'unit_cost'])->groupBy('drug_id');
        $drugs = Drug::whereIn('id', $dispensed->keys()->merge($stock->keys())->unique())->get()->keyBy('id');

        $rows = $drugs->map(fn (Drug $d) => [
            'drug' => $d->label,
            'dispensed' => (int) ($dispensed[$d->id] ?? 0),
            'on_hand' => (int) ($stock->get($d->id)?->sum('quantity_on_hand') ?? 0),
            'stock_value' => round((float) ($stock->get($d->id)?->sum(fn ($b) => $b->quantity_on_hand * (float) $b->unit_cost) ?? 0), 2),
        ])->sortByDesc('dispensed')->values()->all();

        return [
            'columns' => ['drug' => ['Drug', 'text'], 'dispensed' => ['Dispensed (units)', 'int'], 'on_hand' => ['Usable stock', 'int'], 'stock_value' => ['Stock value (cost)', 'money']],
            'rows' => $rows,
            'chart' => $this->barChart('Most dispensed (units)', 'units', array_slice(array_filter($rows, fn ($r) => $r['dispensed'] > 0), 0, 15), 'drug', 'dispensed'),
            'tiles' => [['Units dispensed', array_sum(array_column($rows, 'dispensed')), 'int'], ['Stock value at cost', array_sum(array_column($rows, 'stock_value')), 'money']],
        ];
    }

    // ------------------------------------------------------------------ Clinical

    protected function reportDiagnoses(Carbon $from, Carbon $to, array $f): array
    {
        $dx = Diagnosis::with(['consultation.visit:id,clinic_id', 'consultation.patient:id,gender,date_of_birth'])
            ->whereIn('certainty', ['confirmed', 'provisional'])
            ->whereHas('consultation', fn ($q) => $q->where('status', 'signed')->whereBetween('signed_at', [$from, $to])
                ->when($f['clinic_id'] ?? null, fn ($q, $v) => $q->whereHas('visit', fn ($v2) => $v2->where('clinic_id', $v))))
            ->get();

        $ageGroup = function ($d) {
            $dob = $d->consultation->patient->date_of_birth;
            $age = $dob ? $dob->diffInYears($d->consultation->signed_at) : null;

            return match (true) { $age === null => 'unknown', $age < 5 => 'u5', $age < 15 => '5_14', $age < 50 => '15_49', default => '50p' };
        };

        $rows = $dx->groupBy(fn ($d) => $d->icd10_code ?? mb_strtolower($d->description))->map(function (Collection $g) use ($ageGroup) {
            $ages = $g->map($ageGroup)->countBy();
            $first = $g->first();

            return [
                'code' => $first->icd10_code ?? '', 'diagnosis' => $first->description, 'cases' => $g->count(),
                'u5' => $ages['u5'] ?? 0, '5_14' => $ages['5_14'] ?? 0, '15_49' => $ages['15_49'] ?? 0, '50p' => $ages['50p'] ?? 0,
                'male' => $g->filter(fn ($d) => $d->consultation->patient->gender === 'male')->count(),
                'female' => $g->filter(fn ($d) => $d->consultation->patient->gender === 'female')->count(),
            ];
        })->sortByDesc('cases')->values()->all();

        return [
            'columns' => ['code' => ['ICD-10', 'text'], 'diagnosis' => ['Diagnosis', 'text'], 'cases' => ['Cases', 'int'],
                'u5' => ['<5 yrs', 'int'], '5_14' => ['5–14', 'int'], '15_49' => ['15–49', 'int'], '50p' => ['50+', 'int'],
                'male' => ['Male', 'int'], 'female' => ['Female', 'int']],
            'rows' => $rows,
            'chart' => $this->barChart('Top 15 diagnoses', 'cases', array_slice($rows, 0, 15), 'diagnosis', 'cases'),
            'tiles' => [['Diagnoses recorded', $dx->count(), 'int'], ['Distinct conditions', count($rows), 'int']],
        ];
    }

    protected function reportInvestigations(Carbon $from, Carbon $to, array $f): array
    {
        $items = LabOrderItem::with(['test:id,name,category', 'results:id,lab_order_item_id,flag', 'order:id,created_at,completed_at'])
            ->where('status', 'verified')->whereHas('order', fn ($q) => $q->whereBetween('completed_at', [$from, $to]))->get();

        $rows = $items->groupBy('lab_test_id')->map(function (Collection $g) {
            $tat = $g->map(fn ($i) => $i->order->created_at->diffInMinutes($i->order->completed_at) / 60);

            return [
                'type' => 'Lab · '.$g->first()->test->category, 'name' => $g->first()->test->name, 'count' => $g->count(),
                'abnormal_rate' => round(100 * $g->filter(fn ($i) => $i->results->contains(fn ($r) => $r->flag))->count() / $g->count(), 1),
                'tat' => round($tat->avg(), 1),
            ];
        })->values();

        $imaging = ImagingOrder::with('test:id,name,modality')->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->get()
            ->groupBy('imaging_test_id')->map(fn (Collection $g) => [
                'type' => 'Imaging · '.$g->first()->test->modality, 'name' => $g->first()->test->name, 'count' => $g->count(),
                'abnormal_rate' => null, 'tat' => round($g->avg(fn ($o) => $o->created_at->diffInMinutes($o->completed_at) / 60), 1),
            ])->values();

        $rows = $rows->concat($imaging)->sortByDesc('count')->values()->all();

        return [
            'columns' => ['type' => ['Type', 'text'], 'name' => ['Test / examination', 'text'], 'count' => ['Performed', 'int'],
                'abnormal_rate' => ['Abnormal', 'pct'], 'tat' => ['Avg turnaround (h)', 'dec1']],
            'rows' => $rows,
            'chart' => $this->barChart('Most requested (top 15)', 'performed', array_slice($rows, 0, 15), 'name', 'count'),
            'tiles' => [['Lab tests released', $items->count(), 'int'], ['Imaging reports', $imaging->sum('count'), 'int']],
        ];
    }

    protected function reportAdmissions(Carbon $from, Carbon $to, array $f): array
    {
        $scope = fn ($q) => $q->when($f['ward_id'] ?? null, fn ($q, $v) => $q->where('ward_id', $v));
        $admitted = Admission::with('ward:id,name')->whereBetween('admitted_at', [$from, $to])->tap($scope)->get();
        $discharged = Admission::with('ward:id,name')->whereBetween('discharged_at', [$from, $to])->tap($scope)->get();

        $wards = $admitted->pluck('ward.name')->merge($discharged->pluck('ward.name'))->unique()->sort()->values();
        $rows = $wards->map(function ($ward) use ($admitted, $discharged) {
            $d = $discharged->where('ward.name', $ward);

            return [
                'ward' => $ward, 'admitted' => $admitted->where('ward.name', $ward)->count(), 'discharged' => $d->count(),
                'avg_los' => $d->isEmpty() ? null : round($d->avg(fn ($a) => $a->lengthOfStay()), 1),
                'died' => $d->where('discharge_type', 'died')->count(),
                'dama' => $d->whereIn('discharge_type', ['dama', 'absconded'])->count(),
                'referred' => $d->where('discharge_type', 'referred')->count(),
            ];
        })->all();

        $deaths = $discharged->where('discharge_type', 'died')->count();

        return [
            'columns' => ['ward' => ['Ward', 'text'], 'admitted' => ['Admitted', 'int'], 'discharged' => ['Discharged', 'int'], 'avg_los' => ['Avg stay (days)', 'dec1'],
                'died' => ['Deaths', 'int'], 'dama' => ['Left against advice / absconded', 'int'], 'referred' => ['Referred out', 'int']],
            'rows' => $rows,
            'chart' => $this->barChart('Admissions by ward', 'admissions', $rows, 'ward', 'admitted'),
            'tiles' => [['Admissions', $admitted->count(), 'int'], ['Discharges', $discharged->count(), 'int'],
                ['Avg length of stay (days)', $discharged->isEmpty() ? null : round($discharged->avg(fn ($a) => $a->lengthOfStay()), 1), 'dec1'],
                ['Mortality', $discharged->isEmpty() ? null : round(100 * $deaths / $discharged->count(), 1), 'pct'],
                ['Currently admitted', Admission::current()->tap($scope)->count(), 'int']],
        ];
    }

    protected function reportMaternity(Carbon $from, Carbon $to, array $f): array
    {
        $bookings = \App\Models\Pregnancy::whereBetween('created_at', [$from, $to])->get(['risk_factors']);
        $deliveries = \App\Models\Delivery::with('babies')->whereBetween('delivered_at', [$from, $to])->get();
        $babies = $deliveries->flatMap->babies;

        $rows = collect(config('emr.maternity.delivery_modes'))->map(function ($label, $mode) use ($deliveries) {
            $g = $deliveries->where('mode', $mode);
            $b = $g->flatMap->babies;

            return [
                'mode' => $label, 'deliveries' => $g->count(),
                'live' => $b->where('outcome', 'live_birth')->count(),
                'stillbirths' => $b->whereIn('outcome', ['fresh_stillbirth', 'macerated_stillbirth'])->count(),
                'lbw' => $b->filter(fn ($x) => $x->isLowBirthWeight())->count(),
                'pph' => $g->filter(fn ($d) => in_array('pph', $d->complications ?? [], true))->count(),
                'maternal_deaths' => $g->where('maternal_outcome', 'died')->count(),
            ];
        })->values()->all();

        $total = $deliveries->count();

        return [
            'columns' => ['mode' => ['Mode of delivery', 'text'], 'deliveries' => ['Deliveries', 'int'], 'live' => ['Live births', 'int'],
                'stillbirths' => ['Stillbirths', 'int'], 'lbw' => ['Low birth weight', 'int'], 'pph' => ['PPH', 'int'], 'maternal_deaths' => ['Maternal deaths', 'int']],
            'rows' => $rows,
            'chart' => $this->barChart('Deliveries by mode', 'deliveries', array_filter($rows, fn ($r) => $r['deliveries'] > 0), 'mode', 'deliveries'),
            'tiles' => [
                ['ANC bookings', $bookings->count(), 'int'],
                ['High-risk bookings', $bookings->filter(fn ($p) => ! empty($p->risk_factors))->count(), 'int'],
                ['Deliveries', $total, 'int'],
                ['Caesarean rate', $total ? round(100 * $deliveries->filter->isCaesarean()->count() / $total, 1) : null, 'pct'],
                ['Babies born', $babies->count(), 'int'],
                ['Maternal deaths', $deliveries->where('maternal_outcome', 'died')->count(), 'int'],
            ],
        ];
    }

    protected function reportImmunizations(Carbon $from, Carbon $to, array $f): array
    {
        $given = \App\Models\Immunization::whereBetween('given_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('vaccine_id, COUNT(*) as n')->groupBy('vaccine_id')->pluck('n', 'vaccine_id');

        $rows = \App\Models\Vaccine::scheduled()->get()->map(fn ($v) => [
            'age' => $v->ageLabel(), 'vaccine' => $v->label, 'doses' => (int) ($given[$v->id] ?? 0),
        ])->all();

        return [
            'columns' => ['age' => ['Schedule', 'text'], 'vaccine' => ['Vaccine', 'text'], 'doses' => ['Doses given', 'int']],
            'rows' => $rows,
            'chart' => $this->barChart('Doses given', 'doses', array_filter($rows, fn ($r) => $r['doses'] > 0), 'vaccine', 'doses'),
            'tiles' => [['Doses given', array_sum(array_column($rows, 'doses')), 'int'],
                ['Children with overdue doses (now)', app(ImmunizationService::class)->defaulters()->count(), 'int']],
        ];
    }

    protected function reportSpecialty(Carbon $from, Carbon $to, array $f): array
    {
        $dental = \App\Models\DentalFinding::with('service:id,name')->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->get();
        $eye = \App\Models\EyeExam::whereBetween('created_at', [$from, $to])->get();
        $sessions = \App\Models\PhysioSession::whereBetween('session_date', [$from->toDateString(), $to->toDateString()])->get();
        $episodes = \App\Models\PhysioEpisode::whereBetween('created_at', [$from, $to])->count();
        $discharged = \App\Models\PhysioEpisode::whereBetween('discharged_at', [$from, $to])->get();

        $rows = [];
        foreach ($dental->groupBy(fn ($d) => $d->service?->name ?? 'Other treatment') as $name => $g) {
            $rows[] = ['clinic' => 'Dental', 'activity' => $name, 'count' => $g->count(), 'patients' => $g->unique('patient_id')->count()];
        }
        $rows[] = ['clinic' => 'Eye', 'activity' => 'Eye examinations', 'count' => $eye->count(), 'patients' => $eye->unique('patient_id')->count()];
        $rows[] = ['clinic' => 'Eye', 'activity' => 'Spectacles prescribed', 'count' => $eye->where('spectacles_prescribed', true)->count(), 'patients' => $eye->where('spectacles_prescribed', true)->unique('patient_id')->count()];
        $rows[] = ['clinic' => 'Eye', 'activity' => 'Raised eye pressure (> '.\App\Models\EyeExam::IOP_HIGH.' mmHg)',
            'count' => $eye->filter(fn ($e) => $e->iop_right > \App\Models\EyeExam::IOP_HIGH || $e->iop_left > \App\Models\EyeExam::IOP_HIGH)->count(), 'patients' => null];
        $rows[] = ['clinic' => 'Physiotherapy', 'activity' => 'New assessments', 'count' => $episodes, 'patients' => null];
        $rows[] = ['clinic' => 'Physiotherapy', 'activity' => 'Sessions', 'count' => $sessions->count(), 'patients' => null];
        foreach ($discharged->groupBy('outcome') as $outcome => $g) {
            $rows[] = ['clinic' => 'Physiotherapy', 'activity' => 'Discharged — '.(config("emr.specialty.physio_outcomes.{$outcome}") ?? $outcome), 'count' => $g->count(), 'patients' => null];
        }

        $painDrops = \App\Models\PhysioEpisode::with('sessions')->whereIn('id', $discharged->pluck('id'))->get()
            ->filter(fn ($e) => $e->pain_initial !== null && $e->latestPain() !== null)
            ->map(fn ($e) => $e->pain_initial - $e->latestPain());

        return [
            'columns' => ['clinic' => ['Clinic', 'text'], 'activity' => ['Activity', 'text'], 'count' => ['Count', 'int', false], 'patients' => ['Patients', 'int', false]],
            'rows' => $rows,
            'tiles' => [
                ['Dental treatments done', $dental->count(), 'int'],
                ['Eye examinations', $eye->count(), 'int'],
                ['Physiotherapy sessions', $sessions->count(), 'int'],
                ['Avg pain reduction at discharge', $painDrops->isEmpty() ? null : round($painDrops->avg(), 1), 'dec1'],
            ],
        ];
    }

    protected function reportTheatre(Carbon $from, Carbon $to, array $f): array
    {
        $all = \App\Models\Surgery::with('surgeon:id,name')->whereBetween('scheduled_at', [$from, $to])->get();
        $done = $all->where('status', 'completed');

        $rows = $done->groupBy('procedure_name')->map(fn (Collection $g, $name) => [
            'procedure' => $name, 'operations' => $g->count(),
            'emergency' => $g->where('urgency', 'emergency')->count(),
            'avg_minutes' => $g->map->durationMinutes()->filter()->avg() !== null ? round($g->map->durationMinutes()->filter()->avg()) : null,
            'avg_blood_loss' => $g->whereNotNull('blood_loss_ml')->isEmpty() ? null : round($g->whereNotNull('blood_loss_ml')->avg('blood_loss_ml')),
        ])->sortByDesc('operations')->values()->all();

        $surgeonTiles = $done->groupBy(fn ($s) => $s->surgeon?->name ?? '—')->map->count()->sortDesc()->take(5)
            ->map(fn ($n, $name) => ["Surgeon: {$name}", $n, 'int'])->values()->all();

        return [
            'columns' => ['procedure' => ['Procedure', 'text'], 'operations' => ['Completed', 'int'], 'emergency' => ['Emergencies', 'int'],
                'avg_minutes' => ['Avg time in theatre (min)', 'int', false], 'avg_blood_loss' => ['Avg blood loss (ml)', 'int', false]],
            'rows' => $rows,
            'chart' => $this->barChart('Operations by procedure', 'operations', array_slice($rows, 0, 15), 'procedure', 'operations'),
            'tiles' => array_merge([
                ['Operations completed', $done->count(), 'int'],
                ['Emergency share', $done->count() ? round(100 * $done->where('urgency', 'emergency')->count() / $done->count(), 1) : null, 'pct'],
                ['Cancelled / postponed', $all->whereIn('status', ['cancelled', 'postponed'])->count(), 'int'],
            ], $surgeonTiles),
        ];
    }

    // ------------------------------------------------------------------ Finance

    protected function reportRevenue(Carbon $from, Carbon $to, array $f): array
    {
        $items = BillItem::active()->whereBetween('created_at', [$from, $to])->with('billable')->get();

        $category = fn (BillItem $i) => match (true) {
            $i->billable instanceof Service => $i->billable->category,
            $i->billable instanceof LabTest => 'Laboratory',
            $i->billable instanceof \App\Models\ImagingTest => 'Imaging',
            $i->billable instanceof Drug => 'Pharmacy',
            default => 'Other',
        };

        $rows = $items->groupBy($category)->map(fn (Collection $g, $cat) => [
            'category' => $cat, 'items' => $g->count(), 'amount' => round($g->sum('amount'), 2), 'insurance' => round($g->sum('insurance_amount'), 2),
            'patient' => round($g->sum('patient_amount'), 2), 'discount' => round($g->sum('discount_amount'), 2), 'paid' => round($g->sum('paid_amount'), 2),
        ])->sortByDesc('amount')->values()->all();

        return [
            'columns' => ['category' => ['Service type', 'text'], 'items' => ['Charges', 'int'], 'amount' => ['Gross', 'money'], 'insurance' => ['Insurance share', 'money'],
                'patient' => ['Patient share', 'money'], 'discount' => ['Discounts', 'money'], 'paid' => ['Paid so far', 'money']],
            'rows' => $rows,
            'chart' => $this->barChart('Gross charges by service type', setting('currency_code'), $rows, 'category', 'amount'),
            'tiles' => [['Gross charges', $items->sum('amount'), 'money'], ['Insurance share', $items->sum('insurance_amount'), 'money'],
                ['Discounts / waivers', $items->sum('discount_amount'), 'money']],
        ];
    }

    protected function reportCollections(Carbon $from, Carbon $to, array $f): array
    {
        $payments = Payment::with('receiver:id,name')->whereNull('voided_at')->whereBetween('created_at', [$from, $to])->get();
        $byDay = $payments->groupBy(fn ($p) => $p->created_at->toDateString());

        $rows = [];
        foreach ($this->days($from, $to) as $day) {
            $g = $byDay->get($day, collect());
            $row = ['date' => $day, 'total' => round($g->sum('amount'), 2), 'count' => $g->count()];
            foreach (array_keys(Payment::METHODS) as $m) {
                $row[$m] = round($g->where('method', $m)->sum('amount'), 2);
            }
            $rows[] = $row;
        }

        $columns = ['date' => ['Date', 'date'], 'count' => ['Receipts', 'int']];
        foreach (Payment::METHODS as $m => $label) {
            $columns[$m] = [$label, 'money'];
        }
        $columns['total'] = ['Total', 'money'];

        $byCashier = $payments->groupBy(fn ($p) => $p->receiver?->name ?? '—')->map(fn ($g) => round($g->sum('amount'), 2))->sortDesc();

        return [
            'columns' => $columns,
            'rows' => $rows,
            'chart' => $this->lineChart('Collections per day', setting('currency_code'), $rows, 'date', [['Collected', 'total']]),
            'tiles' => array_merge([['Total collected', $payments->sum('amount'), 'money'], ['Receipts', $payments->count(), 'int']],
                $byCashier->map(fn ($sum, $name) => ["Cashier: {$name}", $sum, 'money'])->values()->all()),
        ];
    }

    protected function reportOutstanding(Carbon $from, Carbon $to, array $f): array
    {
        // Balances as of now; the date range is not applied (debt is cumulative).
        $items = BillItem::unpaid()->with('bill.patient:id,first_name,last_name,hospital_number,phone,payment_type')->get();

        $rows = $items->groupBy('bill.patient_id')->map(function (Collection $g) {
            $age = fn ($i) => $i->created_at->diffInDays(now());
            $sum = fn ($filter) => round($g->filter($filter)->sum(fn ($i) => $i->outstanding()), 2);
            $p = $g->first()->bill->patient;

            return [
                'patient' => $p->list_name, 'hospital_number' => $p->hospital_number, 'phone' => $p->phone,
                'd0_30' => $sum(fn ($i) => $age($i) <= 30), 'd31_60' => $sum(fn ($i) => $age($i) > 30 && $age($i) <= 60),
                'd61_90' => $sum(fn ($i) => $age($i) > 60 && $age($i) <= 90), 'd90p' => $sum(fn ($i) => $age($i) > 90),
                'total' => round($g->sum(fn ($i) => $i->outstanding()), 2),
            ];
        })->sortByDesc('total')->values()->all();

        return [
            'columns' => ['patient' => ['Patient', 'text'], 'hospital_number' => ['Hospital no.', 'text'], 'phone' => ['Phone', 'text'],
                'd0_30' => ['0–30 days', 'money'], 'd31_60' => ['31–60', 'money'], 'd61_90' => ['61–90', 'money'], 'd90p' => ['90+', 'money'], 'total' => ['Total owed', 'money']],
            'rows' => $rows,
            'tiles' => [['Total outstanding', array_sum(array_column($rows, 'total')), 'money'], ['Patients owing', count($rows), 'int'],
                ['Over 90 days', array_sum(array_column($rows, 'd90p')), 'money']],
            'note' => 'Balances as of today; the date filter does not apply.',
        ];
    }

    protected function reportClaims(Carbon $from, Carbon $to, array $f): array
    {
        $bills = Bill::with(['insuranceProvider:id,name', 'items'])->where('claim_status', '!=', 'none')->whereBetween('created_at', [$from, $to])->get();
        // Batched bills use the amount actually claimed; others the current insurer share.
        $claimed = fn (Bill $b) => $b->claim_amount ?? $b->totals()['insurance'];

        $rows = $bills->groupBy(fn ($b) => $b->insuranceProvider?->name ?? '—')->map(function (Collection $g, $payer) use ($claimed) {
            $share = fn (array $statuses) => round($g->whereIn('claim_status', $statuses)->sum($claimed), 2);

            return ['payer' => $payer, 'bills' => $g->count(), 'pending' => $share(['pending']), 'submitted' => $share(['submitted']),
                'received' => round($g->sum('claim_amount_paid'), 2), 'shortfall' => round($g->sum(fn ($b) => $b->claimShortfall()), 2),
                'total' => round($g->sum($claimed), 2)];
        })->sortByDesc('total')->values()->all();

        $total = array_sum(array_column($rows, 'total'));
        $shortfall = array_sum(array_column($rows, 'shortfall'));

        return [
            'columns' => ['payer' => ['Insurer / company', 'text'], 'bills' => ['Bills', 'int', true], 'pending' => ['To submit', 'money', true],
                'submitted' => ['Awaiting payment', 'money', true], 'received' => ['Received', 'money', true], 'shortfall' => ['Rejected / short-paid', 'money', true],
                'total' => ['Total claimed', 'money', true]],
            'rows' => $rows,
            'chart' => $this->barChart('Claim value by payer', setting('currency_code'), $rows, 'payer', 'total'),
            'tiles' => [['Total insurer share', $total, 'money'], ['Awaiting payment', array_sum(array_column($rows, 'submitted')), 'money'],
                ['Received', array_sum(array_column($rows, 'received')), 'money'],
                ['Rejection rate', $total > 0 ? round($shortfall / $total * 100, 1) : 0, 'pct']],
        ];
    }

    protected function reportClaimsAgeing(Carbon $from, Carbon $to, array $f): array
    {
        // Submitted claims not yet paid, aged from the submission date (as of today).
        $bills = Bill::with('insuranceProvider:id,name')->where('claim_status', 'submitted')->get();

        $rows = $bills->groupBy(fn ($b) => $b->insuranceProvider?->name ?? '—')->map(function (Collection $g, $payer) {
            $age = fn (Bill $b) => (int) ($b->claim_submitted_at ?? $b->created_at)->diffInDays(now());
            $sum = fn ($filter) => round($g->filter($filter)->sum('claim_amount'), 2);

            return ['payer' => $payer, 'bills' => $g->count(),
                'd0_30' => $sum(fn ($b) => $age($b) <= 30), 'd31_60' => $sum(fn ($b) => $age($b) > 30 && $age($b) <= 60),
                'd61_90' => $sum(fn ($b) => $age($b) > 60 && $age($b) <= 90), 'd90p' => $sum(fn ($b) => $age($b) > 90),
                'total' => round($g->sum('claim_amount'), 2), 'oldest' => $g->max($age)];
        })->sortByDesc('total')->values()->all();

        return [
            'columns' => ['payer' => ['Insurer / company', 'text'], 'bills' => ['Bills', 'int', true], 'd0_30' => ['0–30 days', 'money', true],
                'd31_60' => ['31–60', 'money', true], 'd61_90' => ['61–90', 'money', true], 'd90p' => ['90+', 'money', true],
                'total' => ['Total owed', 'money', true], 'oldest' => ['Oldest (days)', 'int', false]],
            'rows' => $rows,
            'chart' => $this->barChart('Unpaid claims by payer', setting('currency_code'), $rows, 'payer', 'total'),
            'tiles' => [['Owed by insurers', array_sum(array_column($rows, 'total')), 'money'], ['Over 90 days', array_sum(array_column($rows, 'd90p')), 'money']],
            'note' => 'Submitted claims still awaiting payment, aged from submission; the date filter does not apply.',
        ];
    }

    // ------------------------------------------------------------------ Staff

    protected function reportStores(Carbon $from, Carbon $to, array $f): array
    {
        $issues = \App\Models\StoreMovement::with(['department:id,name', 'item:id,name,category'])->where('type', 'issue')->whereBetween('created_at', [$from, $to])->get();
        $value = fn (Collection $g) => round($g->sum(fn ($m) => abs($m->quantity) * (float) $m->unit_cost), 2);

        $rows = $issues->groupBy(fn ($m) => $m->department?->name ?? '—')->map(fn (Collection $g, $dept) => [
            'department' => $dept,
            'issues' => $g->pluck('reference_id')->unique()->count(),
            'lines' => $g->count(),
            'value' => $value($g),
            'top' => $g->groupBy(fn ($m) => $m->item->name)->map($value)->sortDesc()->keys()->take(3)->implode(', '),
        ])->sortByDesc('value')->values()->all();

        $stockValue = (float) \App\Models\StoreItem::active()->where('quantity_on_hand', '>', 0)->sum(DB::raw('quantity_on_hand * average_cost'));

        return [
            'columns' => ['department' => ['Department', 'text'], 'issues' => ['Requisitions', 'int'], 'lines' => ['Item lines', 'int'],
                'value' => ['Value issued', 'money'], 'top' => ['Biggest items', 'text']],
            'rows' => $rows,
            'chart' => $this->barChart('Value issued by department', setting('currency_code'), $rows, 'department', 'value'),
            'tiles' => [['Value issued', array_sum(array_column($rows, 'value')), 'money'], ['Stock value now', $stockValue, 'money'],
                ['Items at/below reorder level', \App\Models\StoreItem::lowStock()->count(), 'int']],
        ];
    }

    protected function reportProcurement(Carbon $from, Carbon $to, array $f): array
    {
        // Goods received in the period, valued at order price.
        $received = \App\Models\StoreMovement::where('type', 'receipt')->where('reference_type', (new \App\Models\PurchaseOrder)->getMorphClass())
            ->whereBetween('created_at', [$from, $to])->get()->groupBy('reference_id')
            ->map(fn ($g) => $g->sum(fn ($m) => $m->quantity * (float) $m->unit_cost));
        $drugReceived = \App\Models\StockReceipt::with('batches')->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('supplier_id')->get()->groupBy('supplier_id')
            ->map(fn ($g) => $g->flatMap->batches->sum(fn ($b) => $b->quantity_received * (float) $b->unit_cost));
        $orders = \App\Models\PurchaseOrder::whereIn('id', $received->keys())->pluck('supplier_id', 'id');

        $invoices = \App\Models\SupplierInvoice::get();
        $suppliers = \App\Models\Supplier::orderBy('name')->get();

        $rows = $suppliers->map(function ($s) use ($received, $drugReceived, $orders, $invoices, $from, $to) {
            $storeValue = $received->filter(fn ($v, $poId) => ($orders[$poId] ?? null) === $s->id)->sum();
            $mine = $invoices->where('supplier_id', $s->id);

            return [
                'supplier' => $s->name,
                'store_goods' => round($storeValue, 2),
                'drug_goods' => round((float) ($drugReceived[$s->id] ?? 0), 2),
                'invoiced' => round($mine->filter(fn ($i) => $i->invoice_date->between($from, $to))->sum('amount'), 2),
                'paid' => round($mine->filter(fn ($i) => $i->paid_on?->between($from, $to))->sum('amount'), 2),
                'owed' => round($mine->where('status', 'unpaid')->sum('amount'), 2),
                'overdue' => round($mine->filter->isOverdue()->sum('amount'), 2),
            ];
        })->filter(fn ($r) => $r['store_goods'] + $r['drug_goods'] + $r['invoiced'] + $r['paid'] + $r['owed'] > 0)->values()->all();

        return [
            'columns' => ['supplier' => ['Supplier', 'text'], 'store_goods' => ['Store goods received', 'money'], 'drug_goods' => ['Drugs received', 'money'],
                'invoiced' => ['Invoiced', 'money'], 'paid' => ['Paid', 'money'], 'owed' => ['Owed now', 'money'], 'overdue' => ['Overdue now', 'money']],
            'rows' => $rows,
            'tiles' => [['Goods received', array_sum(array_column($rows, 'store_goods')) + array_sum(array_column($rows, 'drug_goods')), 'money'],
                ['Paid to suppliers', array_sum(array_column($rows, 'paid')), 'money'], ['Owed now', array_sum(array_column($rows, 'owed')), 'money'],
                ['Overdue now', array_sum(array_column($rows, 'overdue')), 'money']],
            'note' => '"Owed now" and "Overdue now" are as of today; other columns use the date range.',
        ];
    }

    protected function reportStaff(Carbon $from, Carbon $to, array $f): array
    {
        $range = [$from, $to];
        $count = fn ($query, string $userCol) => $query->whereNotNull($userCol)->selectRaw("$userCol as uid, COUNT(*) as n")->groupBy($userCol)->pluck('n', 'uid');

        $metrics = [
            'logins' => $count(AuditLog::where('event', 'login')->whereBetween('created_at', $range), 'user_id'),
            'registered' => $count(Patient::whereBetween('created_at', $range), 'registered_by'),
            'checked_in' => $count(Visit::whereBetween('checked_in_at', $range), 'checked_in_by'),
            'consultations' => $count(Consultation::where('status', 'signed')->whereBetween('signed_at', $range), 'doctor_id'),
            'vitals' => $count(DB::table('vital_signs')->whereNull('voided_at')->whereBetween('recorded_at', $range), 'recorded_by'),
            'lab_results' => $count(LabOrderItem::whereBetween('entered_at', $range), 'entered_by'),
            'rx_dispensed' => $count(\App\Models\Prescription::whereBetween('dispensed_at', $range), 'dispensed_by'),
            'receipts' => $count(Payment::whereNull('voided_at')->whereBetween('created_at', $range), 'received_by'),
        ];

        $ids = collect($metrics)->flatMap(fn ($m) => $m->keys())->unique();
        $rows = User::with('roles:id,name')->whereIn('id', $ids)->orderBy('name')->get()->map(fn (User $u) => array_merge(
            ['name' => $u->name, 'role' => $u->roles->pluck('name')->implode(', ')],
            collect($metrics)->map(fn ($m) => (int) ($m[$u->id] ?? 0))->all()
        ))->all();

        return [
            'columns' => ['name' => ['Staff', 'text'], 'role' => ['Role', 'text'], 'logins' => ['Sign-ins', 'int'], 'registered' => ['Patients registered', 'int'],
                'checked_in' => ['Check-ins', 'int'], 'consultations' => ['Consultations signed', 'int'], 'vitals' => ['Vitals recorded', 'int'],
                'lab_results' => ['Lab results entered', 'int'], 'rx_dispensed' => ['Prescriptions dispensed', 'int'], 'receipts' => ['Receipts issued', 'int']],
            'rows' => $rows,
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return list<string> Y-m-d for each day in range (capped at 366)
     */
    protected function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($d = $from->copy(); $d->lte($to) && count($days) < 366; $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return $days;
    }

    protected function lineChart(string $title, string $unit, array $rows, string $labelKey, array $series): ?array
    {
        if (count($rows) < 2) {
            return null;
        }

        return [
            'type' => 'line', 'title' => $title, 'unit' => $unit,
            'labels' => array_map(fn ($r) => Carbon::parse($r[$labelKey])->format('d M'), $rows),
            'series' => array_map(fn ($s) => ['label' => $s[0], 'data' => array_column($rows, $s[1])], $series),
        ];
    }

    protected function barChart(string $title, string $unit, array $rows, string $labelKey, string $valueKey): ?array
    {
        $rows = array_values($rows);
        if (! $rows) {
            return null;
        }

        return [
            'type' => 'bar', 'horizontal' => true, 'title' => $title, 'unit' => $unit,
            'labels' => array_map(fn ($r) => mb_strimwidth((string) $r[$labelKey], 0, 40, '…'), $rows),
            'series' => [['label' => $title, 'data' => array_column($rows, $valueKey)]],
        ];
    }
}
