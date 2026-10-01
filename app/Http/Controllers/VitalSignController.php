<?php

namespace App\Http\Controllers;

use App\Models\NursingNote;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VitalSignController extends Controller
{
    public const MEASUREMENTS = [
        'temperature', 'systolic', 'diastolic', 'pulse', 'respiratory_rate', 'spo2',
        'weight', 'height', 'pain_score', 'blood_glucose',
    ];

    /**
     * Triage worklist: everyone waiting for a nurse.
     */
    public function worklist(Request $request): View
    {
        return view('vitals.worklist', [
            'waiting' => Visit::where('status', Visit::WAITING_TRIAGE)->with(['patient', 'clinic'])->queueOrder()->get(),
            'recent' => VitalSign::with(['patient', 'recorder'])->valid()
                ->where('recorded_at', '>=', now()->subHours(12))->latest('recorded_at')->limit(15)->get(),
        ]);
    }

    public function triage(Visit $visit): View|RedirectResponse
    {
        if ($visit->status !== Visit::WAITING_TRIAGE) {
            return redirect()->route('vitals.create', $visit->patient)
                ->with('info', "{$visit->queue_number} is no longer waiting for triage; recording vitals without moving the queue.");
        }

        return view('vitals.triage', $this->formData($visit->patient, $visit));
    }

    public function storeTriage(Request $request, Visit $visit, QueueService $queue): RedirectResponse
    {
        $data = $this->validated($request, withTriage: true);

        DB::transaction(function () use ($data, $request, $visit, $queue) {
            $this->record($visit->patient, $visit, $data, $request);

            if ($data['priority'] !== $visit->priority) {
                $visit->update(['priority' => $data['priority']]);
            }

            if ($request->boolean('send_to_doctor') && $visit->canMoveTo(Visit::WAITING_DOCTOR)) {
                $queue->move($visit, Visit::WAITING_DOCTOR, $request->user());
            }
        });

        $message = "Vitals recorded for {$visit->patient->full_name}"
            .($visit->status === Visit::WAITING_DOCTOR ? ' and sent to the doctor.' : '.');

        return redirect()->route('vitals.worklist')->with('success', $message);
    }

    public function create(Patient $patient): View
    {
        return view('vitals.create', $this->formData($patient, $patient->visits()->open()->first()));
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $this->validated($request);
        $this->record($patient, $patient->visits()->open()->first(), $data, $request);

        return redirect()->route('vitals.index', $patient)->with('success', 'Vital signs recorded.');
    }

    public function index(Patient $patient): View
    {
        $vitals = $patient->vitalSigns()->with(['recorder', 'voider'])->latest('recorded_at')->paginate(25);
        $chartRows = $patient->vitalSigns()->valid()->oldest('recorded_at')->limit(200)->get()->each->setRelation('patient', $patient);

        return view('vitals.index', [
            'patient' => $patient,
            'vitals' => $vitals->through(fn (VitalSign $v) => $v->setRelation('patient', $patient)),
            'charts' => $this->chartData($chartRows),
            'notes' => $patient->nursingNotes()->with('author')->latest()->limit(50)->get(),
        ]);
    }

    public function void(Request $request, VitalSign $vital): RedirectResponse
    {
        $user = $request->user();
        abort_unless($vital->recorded_by === $user->id || $user->can('vitals.void'), 403, 'You can only void entries you recorded.');
        abort_if($vital->isVoided(), 422, 'Already voided.');

        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);

        $vital->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $data['void_reason']])->saveQuietly();
        Audit::log('vitals_voided', "Vitals #{$vital->id} marked entered-in-error: {$data['void_reason']}", $vital);

        return back()->with('success', 'Entry marked as entered in error.');
    }

    protected function record(Patient $patient, ?Visit $visit, array $data, Request $request): VitalSign
    {
        $vitals = new VitalSign(Arr::except($data, ['priority', 'nursing_note', 'send_to_doctor']));
        $vitals->patient()->associate($patient);
        $vitals->visit_id = $visit?->id;
        $vitals->recorded_by = $request->user()->id;
        $vitals->recorded_at = now();
        $vitals->save();

        if (! empty($data['nursing_note'])) {
            $note = new NursingNote(['type' => $visit ? 'triage' : 'observation', 'note' => $data['nursing_note']]);
            $note->patient_id = $patient->id;
            $note->visit_id = $visit?->id;
            $note->user_id = $request->user()->id;
            $note->save();
        }

        return $vitals;
    }

    protected function validated(Request $request, bool $withTriage = false): array
    {
        $data = $request->validate([
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic' => ['nullable', 'required_with:diastolic', 'integer', 'between:40,300'],
            'diastolic' => ['nullable', 'required_with:systolic', 'integer', 'between:20,200', 'lt:systolic'],
            'pulse' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'on_oxygen' => ['boolean'],
            'consciousness' => ['nullable', Rule::in(array_keys(VitalSign::CONSCIOUSNESS))],
            'weight' => ['nullable', 'numeric', 'between:0.3,400'],
            'height' => ['nullable', 'numeric', 'between:20,250'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0.5,50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'nursing_note' => ['nullable', 'string', 'max:2000'],
            'priority' => [$withTriage ? 'required' : 'exclude', Rule::in(array_keys(Visit::PRIORITIES))],
        ], [
            'diastolic.lt' => 'Diastolic must be lower than systolic.',
        ], [
            'spo2' => 'SpO₂',
            'pain_score' => 'pain score',
        ]);

        if (collect(Arr::only($data, self::MEASUREMENTS))->filter(fn ($v) => $v !== null)->isEmpty()) {
            throw ValidationException::withMessages(['temperature' => 'Enter at least one measurement.']);
        }

        $data['on_oxygen'] = $request->boolean('on_oxygen');

        return $data;
    }

    protected function formData(Patient $patient, ?Visit $visit): array
    {
        return [
            'patient' => $patient,
            'visit' => $visit,
            'previous' => $patient->vitalSigns()->valid()->latest('recorded_at')->limit(3)->get()->each->setRelation('patient', $patient),
        ];
    }

    /**
     * One chart per measure (never two scales on one chart).
     */
    protected function chartData($rows): array
    {
        $labels = $rows->map(fn (VitalSign $v) => $v->recorded_at->format('d M H:i'))->all();
        $series = fn (string $field) => $rows->map(fn (VitalSign $v) => $v->{$field})->all();

        $charts = [
            ['id' => 'bp', 'title' => 'Blood pressure', 'unit' => 'mmHg', 'series' => [
                ['label' => 'Systolic', 'data' => $series('systolic')],
                ['label' => 'Diastolic', 'data' => $series('diastolic')],
            ]],
            ['id' => 'pulse', 'title' => 'Pulse', 'unit' => '/min', 'series' => [['label' => 'Pulse', 'data' => $series('pulse')]]],
            ['id' => 'temperature', 'title' => 'Temperature', 'unit' => '°C', 'series' => [['label' => 'Temperature', 'data' => $series('temperature')]]],
            ['id' => 'spo2', 'title' => 'SpO₂', 'unit' => '%', 'series' => [['label' => 'SpO₂', 'data' => $series('spo2')]]],
            ['id' => 'weight', 'title' => 'Weight', 'unit' => 'kg', 'series' => [['label' => 'Weight', 'data' => $series('weight')]]],
        ];

        // Only draw a chart when it has at least two readings to trend.
        return collect($charts)
            ->filter(fn ($c) => collect($c['series'][0]['data'])->filter(fn ($v) => $v !== null)->count() >= 2)
            ->map(fn ($c) => $c + ['labels' => $labels])
            ->values()
            ->all();
    }
}
