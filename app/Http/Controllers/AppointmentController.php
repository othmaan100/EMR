<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Services\QueueService;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString()))->startOfDay(), today(), false);
        $filters = $request->only(['clinic_id', 'doctor_id', 'status']);

        $base = Appointment::whereDate('scheduled_at', $date)
            ->when($filters['clinic_id'] ?? null, fn ($q, $v) => $q->where('clinic_id', $v))
            ->when($filters['doctor_id'] ?? null, fn ($q, $v) => $q->where('doctor_id', $v));

        return view('appointments.index', [
            'date' => $date,
            'filters' => $filters,
            'appointments' => (clone $base)->with(['patient', 'clinic', 'doctor', 'visit'])
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->orderBy('scheduled_at')->get(),
            'counts' => (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'clinics' => Clinic::orderBy('name')->pluck('name', 'id'),
            'doctors' => $this->doctors()->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $appointment = new Appointment([
            'type' => 'new',
            'patient_id' => $request->query('patient_id'),
            'clinic_id' => $request->query('clinic_id'),
            'scheduled_at' => $request->query('date') ? Carbon::parse($request->query('date'))->setTime(9, 0) : null,
        ]);

        return view('appointments.create', $this->formData($appointment));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $appointment = new Appointment($data);
        $appointment->booked_by = $request->user()->id;
        $appointment->save();

        return redirect()->route('appointments.index', ['date' => $appointment->scheduled_at->toDateString()])
            ->with('success', "Appointment booked for {$appointment->patient->full_name} on ".format_date($appointment->scheduled_at, true).'.');
    }

    public function edit(Appointment $appointment): View|RedirectResponse
    {
        if (! $appointment->isScheduled()) {
            return back()->with('error', 'Only scheduled appointments can be changed.');
        }

        return view('appointments.edit', $this->formData($appointment));
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->isScheduled(), 422, 'Only scheduled appointments can be changed.');

        $appointment->update($this->validated($request, $appointment));

        return redirect()->route('appointments.index', ['date' => $appointment->scheduled_at->toDateString()])
            ->with('success', 'Appointment updated.');
    }

    /**
     * Online requests from the patient portal waiting for a date and time.
     */
    public function requests(): View
    {
        return view('appointments.requests', [
            'requests' => Appointment::with(['patient', 'clinic'])->where('status', 'requested')->orderBy('scheduled_at')->get(),
            'doctors' => $this->doctors()->pluck('name', 'id'),
        ]);
    }

    public function confirm(Request $request, Appointment $appointment, SmsService $sms): RedirectResponse
    {
        abort_unless($appointment->status === 'requested', 422);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'doctor_id' => ['nullable', Rule::in($this->doctors()->pluck('id')->all())],
        ]);
        $when = Carbon::parse($data['date'].' '.$data['time']);
        if ($when->lt(now()->subMinutes(5))) {
            throw ValidationException::withMessages(['time' => 'The appointment time has already passed.']);
        }

        $appointment->forceFill(['status' => 'scheduled', 'scheduled_at' => $when, 'doctor_id' => $data['doctor_id'] ?? null, 'booked_by' => $request->user()->id])->save();

        if (setting('sms_appointment_reminders')) {
            $body = $sms->render('Dear {name}, your appointment at {hospital} ({clinic}) is confirmed for {date} at {time}.', $appointment->patient, [
                'clinic' => $appointment->clinic->name, 'date' => format_date($when), 'time' => $when->format('h:i A'),
            ]);
            $sms->queue($appointment->patient, null, $body, 'appointment_confirmed', "apptconf:{$appointment->id}:".$when->format('YmdHi'));
        }

        return back()->with('success', "Confirmed for {$appointment->patient->full_name} on ".format_date($when, true).'.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->isScheduled() || $appointment->status === 'requested', 422);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        $appointment->forceFill(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason']])->save();

        return back()->with('success', 'Appointment cancelled.');
    }

    public function noShow(Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->isScheduled(), 422);

        $appointment->forceFill(['status' => 'no_show'])->save();

        return back()->with('success', 'Marked as no-show.');
    }

    public function checkIn(Request $request, Appointment $appointment, QueueService $queue): RedirectResponse
    {
        if (! $appointment->isScheduled() || ! $appointment->scheduled_at->isToday()) {
            return back()->with('error', "Only today's scheduled appointments can be checked in.");
        }

        $data = $request->validate(['priority' => ['nullable', Rule::in(array_keys(\App\Models\Visit::PRIORITIES))]]);

        $visit = $queue->checkIn($appointment->patient, [
            'clinic_id' => $appointment->clinic_id,
            'doctor_id' => $appointment->doctor_id,
            'visit_type' => $appointment->type === 'follow_up' ? 'follow_up' : 'outpatient',
            'priority' => $data['priority'] ?? 'normal',
            'complaint' => $appointment->reason,
        ], $request->user(), $appointment);

        return back()->with('success', "{$appointment->patient->full_name} checked in. Queue number: {$visit->queue_number}.");
    }

    protected function validated(Request $request, ?Appointment $appointment = null): array
    {
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'clinic_id' => ['required', Rule::exists('clinics', 'id')->where('is_active', true)],
            'doctor_id' => ['nullable', Rule::in($this->doctors()->pluck('id')->all())],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'type' => ['required', Rule::in(array_keys(Appointment::TYPES))],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['doctor_id' => 'doctor']);

        $data['doctor_id'] ??= null;
        $data['scheduled_at'] = Carbon::parse($data['date'].' '.$data['time']);
        unset($data['date'], $data['time']);

        if ($data['scheduled_at']->lt(now()->subMinutes(5))) {
            throw ValidationException::withMessages(['time' => 'The appointment time has already passed.']);
        }

        $clash = fn ($q) => $q->where('status', 'scheduled')->when($appointment, fn ($q) => $q->whereKeyNot($appointment->id));

        if ($data['doctor_id'] && Appointment::where('doctor_id', $data['doctor_id'])->where('scheduled_at', $data['scheduled_at'])->tap($clash)->exists()) {
            throw ValidationException::withMessages(['time' => 'This doctor already has an appointment at that time.']);
        }

        if (Appointment::where('patient_id', $data['patient_id'])->where('clinic_id', $data['clinic_id'])
            ->whereDate('scheduled_at', $data['scheduled_at'])->tap($clash)->exists()) {
            throw ValidationException::withMessages(['date' => 'This patient is already booked into this clinic on that day.']);
        }

        return $data;
    }

    protected function formData(Appointment $appointment): array
    {
        return [
            'appointment' => $appointment,
            // After a failed submit, re-show the patient that was picked.
            'patient' => Patient::find(request()->old('patient_id', $appointment->patient_id)),
            'clinics' => Clinic::active()->orderBy('name')->pluck('name', 'id'),
            'doctors' => $this->doctors()->pluck('name', 'id'),
        ];
    }

    protected function doctors(): Collection
    {
        return User::role('Doctor')->active()->orderBy('name')->get(['id', 'name']);
    }
}
