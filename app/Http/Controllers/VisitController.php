<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function __construct(protected QueueService $queue) {}

    public function create(Patient $patient): View|RedirectResponse
    {
        if ($open = $patient->visits()->open()->first()) {
            return redirect()->route('patients.show', $patient)
                ->with('warning', "Already checked in: {$open->visit_number} in {$open->clinic->name} ({$open->statusLabel()}).");
        }

        return view('visits.create', [
            'patient' => $patient,
            'clinics' => Clinic::active()->orderBy('name')->get(['id', 'name', 'requires_triage']),
            'doctors' => User::role('Doctor')->active()->orderBy('name')->pluck('name', 'id'),
            'todaysAppointment' => $patient->appointments()->where('status', 'scheduled')->whereDate('scheduled_at', today())->with('clinic')->first(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', Rule::exists('clinics', 'id')->where('is_active', true)],
            'doctor_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'visit_type' => ['required', Rule::in(array_keys(Visit::TYPES))],
            'priority' => ['required', Rule::in(array_keys(Visit::PRIORITIES))],
            'complaint' => ['nullable', 'string', 'max:255'],
        ]);

        $visit = $this->queue->checkIn($patient, $data, $request->user());

        return redirect()->route('patients.show', $patient)
            ->with('success', "Checked in to {$visit->clinic->name}. Visit {$visit->visit_number}, queue number {$visit->queue_number}.");
    }

    public function queue(Request $request): View
    {
        $clinics = Clinic::active()->orderBy('name')->get();
        $clinicId = $request->integer('clinic_id') ?: null;
        $mine = $request->boolean('mine');

        $scope = fn ($q) => $q
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($mine, fn ($q) => $q->where(fn ($q) => $q->where('doctor_id', $request->user()->id)->orWhereNull('doctor_id')));

        $open = Visit::open()->tap($scope)->with(['patient', 'clinic', 'doctor', 'latestVitals'])->queueOrder()->get()->groupBy('status');
        // Vitals flags depend on the patient's age; reuse the loaded patient.
        $open->flatten()->each(fn (Visit $v) => $v->latestVitals?->setRelation('patient', $v->patient));

        $done = Visit::whereIn('status', [Visit::COMPLETED, Visit::LEFT, Visit::CANCELLED])
            ->whereDate('completed_at', today())->tap($scope)
            ->with(['patient', 'clinic', 'doctor'])->latest('completed_at')->limit(30)->get();

        $view = $request->boolean('partial') ? 'visits._board' : 'visits.queue';

        return view($view, [
            'clinics' => $clinics,
            'clinicId' => $clinicId,
            'mine' => $mine,
            'columns' => [
                Visit::WAITING_TRIAGE => $open->get(Visit::WAITING_TRIAGE, collect()),
                Visit::WAITING_DOCTOR => $open->get(Visit::WAITING_DOCTOR, collect()),
                Visit::IN_CONSULTATION => $open->get(Visit::IN_CONSULTATION, collect()),
            ],
            'done' => $done,
            'openCounts' => Visit::open()->selectRaw('clinic_id, count(*) as total')->groupBy('clinic_id')->pluck('total', 'clinic_id'),
        ]);
    }

    public function move(Request $request, Visit $visit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Visit::STATUSES))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->queue->move($visit, $data['status'], $request->user(), $data['note'] ?? null);

        // Doctors go straight into the consultation screen.
        if ($visit->status === Visit::IN_CONSULTATION && $request->user()->can('consultations.create')) {
            return redirect()->route('consultations.show', $visit);
        }

        return back()->with('success', "{$visit->patient->full_name} ({$visit->queue_number}) → {$visit->statusLabel()}.");
    }
}
