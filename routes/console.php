<?php

use App\Support\SystemHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The scheduler must run every minute:
// `php artisan schedule:run` (cron on Linux, Task Scheduler on Windows).

// Lets System Health confirm the scheduler is running.
Schedule::call(fn () => Cache::forever(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String()))
    ->everyMinute()->name('scheduler-heartbeat');

// Daily bed charges for inpatients.
Schedule::command('emr:charge-beds')->dailyAt('00:05')->withoutOverlapping();

// SMS: deliver the outbox every minute; queue reminders each morning.
Schedule::command('emr:send-sms')->everyMinute()->withoutOverlapping();
Schedule::command('emr:sms-reminders')->dailyAt('08:00')->withoutOverlapping();

// Link imaging orders to studies arriving in the PACS.
Schedule::command('emr:pacs-sync')->everyFiveMinutes()->withoutOverlapping();

// Nightly backup of the database and patient files.
Schedule::command('emr:backup')->dailyAt('01:30')->withoutOverlapping();
