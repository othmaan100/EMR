<?php

namespace App\Console\Commands;

use App\Services\SmsNotifier;
use Illuminate\Console\Command;

class SmsReminders extends Command
{
    protected $signature = 'emr:sms-reminders';

    protected $description = "Queue appointment (tomorrow) and immunization (in 3 days) SMS reminders (runs daily)";

    public function handle(SmsNotifier $notifier): int
    {
        $appointments = $notifier->appointmentReminders();
        $immunizations = $notifier->immunizationReminders();
        $this->info("Queued {$appointments} appointment and {$immunizations} immunization reminder(s).");

        return self::SUCCESS;
    }
}
