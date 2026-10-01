<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Vaccine;

/**
 * Decides which events produce patient SMS, using the templates and
 * on/off switches in Hospital Settings → SMS.
 */
class SmsNotifier
{
    public const DEFAULT_TEMPLATES = [
        'sms_tpl_appointment' => 'Dear {name}, reminder of your appointment at {hospital} ({clinic}) on {date} at {time}. Call {phone} to change it.',
        'sms_tpl_results' => 'Dear {name}, your {test} results are ready at {hospital}. Please see your doctor. Ref {ref}.',
        'sms_tpl_immunization' => 'Dear parent/carer, {name} is due for {vaccine} on {date} at {hospital}. Please bring the vaccination card.',
    ];

    public function __construct(protected SmsService $sms) {}

    public function resultsReady(Patient $patient, string $what, string $reference): void
    {
        if (! setting('sms_results_ready')) {
            return;
        }

        $body = $this->sms->render($this->template('sms_tpl_results'), $patient, ['test' => $what, 'ref' => $reference]);
        $this->sms->queue($patient, null, $body, 'results_ready', "results:{$reference}");
    }

    /**
     * Tomorrow's appointments. Returns how many reminders were queued.
     */
    public function appointmentReminders(): int
    {
        if (! setting('sms_appointment_reminders')) {
            return 0;
        }

        $count = 0;
        Appointment::with(['patient', 'clinic'])->where('status', 'scheduled')
            ->whereDate('scheduled_at', today()->addDay())->get()
            ->each(function (Appointment $a) use (&$count) {
                $body = $this->sms->render($this->template('sms_tpl_appointment'), $a->patient, [
                    'date' => format_date($a->scheduled_at), 'time' => $a->scheduled_at->format('h:i A'), 'clinic' => $a->clinic->name,
                ]);
                $count += $this->sms->queue($a->patient, null, $body, 'appointment_reminder', "appt:{$a->id}:".$a->scheduled_at->format('YmdHi')) ? 1 : 0;
            });

        return $count;
    }

    /**
     * Doses falling due in 3 days for children enrolled in immunization.
     */
    public function immunizationReminders(): int
    {
        if (! setting('sms_immunization_reminders')) {
            return 0;
        }

        $target = today()->addDays(3);
        $count = 0;
        $vaccines = Vaccine::active()->get();

        foreach ($vaccines as $vaccine) {
            // Children whose date of birth puts this dose exactly 3 days away.
            Patient::with('immunizations')->where('is_deceased', false)
                ->whereDate('date_of_birth', $target->copy()->subDays($vaccine->age_days))
                ->where(fn ($q) => $q->whereNotNull('mother_id')->orWhereHas('immunizations'))
                ->get()
                ->reject(fn (Patient $p) => $p->immunizations->contains('vaccine_id', $vaccine->id))
                ->each(function (Patient $p) use ($vaccine, $target, &$count) {
                    $body = $this->sms->render($this->template('sms_tpl_immunization'), $p, [
                        'vaccine' => $vaccine->label, 'date' => format_date($target),
                    ]);
                    $count += $this->sms->queue($p, $p->nok_phone ?: $p->phone, $body, 'immunization_reminder', "imm:{$p->id}:{$vaccine->id}") ? 1 : 0;
                });
        }

        return $count;
    }

    protected function template(string $key): string
    {
        return setting($key) ?: self::DEFAULT_TEMPLATES[$key];
    }
}
