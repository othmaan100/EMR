<?php

namespace App\Support;

use App\Models\SmsMessage;
use App\Services\BackupService;
use App\Services\SmsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Go-live checks shown on Administration → System Health.
 * status: ok | warn | fail
 */
class SystemHealth
{
    public const HEARTBEAT_KEY = 'emr.scheduler.heartbeat';

    /**
     * @return list<array{label: string, status: string, detail: string, fix?: string}>
     */
    public function checks(): array
    {
        $checks = [];

        $checks[] = config('app.debug')
            ? ['label' => 'Debug mode', 'status' => 'fail', 'detail' => 'ON — errors reveal internal details, including database credentials.',
                'fix' => 'In .env set APP_DEBUG=false'] : ['label' => 'Debug mode', 'status' => 'ok', 'detail' => 'Off'];

        $checks[] = app()->environment('production')
            ? ['label' => 'Environment', 'status' => 'ok', 'detail' => 'production']
            : ['label' => 'Environment', 'status' => 'warn', 'detail' => app()->environment(), 'fix' => 'In .env set APP_ENV=production'];

        $https = request()->isSecure() || str_starts_with((string) config('app.url'), 'https://');
        $checks[] = $https
            ? ['label' => 'HTTPS', 'status' => 'ok', 'detail' => 'Enabled']
            : ['label' => 'HTTPS', 'status' => 'warn', 'detail' => 'Not in use — traffic on the network is unencrypted and the webcam only works on this computer.',
                'fix' => 'Install a certificate on the web server and set APP_URL to https://…'];

        $beat = Cache::get(self::HEARTBEAT_KEY);
        $checks[] = $beat && Carbon::parse($beat)->gt(now()->subMinutes(5))
            ? ['label' => 'Scheduler', 'status' => 'ok', 'detail' => 'Running (last run '.Carbon::parse($beat)->diffForHumans().')']
            : ['label' => 'Scheduler', 'status' => 'fail', 'detail' => $beat ? 'Last ran '.Carbon::parse($beat)->diffForHumans() : 'Has never run — nightly backups and bed charges will not happen.',
                'fix' => 'Schedule `php artisan schedule:run` every minute (see README → Scheduled tasks)'];

        $latest = app(BackupService::class)->latest();
        $checks[] = match (true) {
            $latest === null => ['label' => 'Backups', 'status' => 'fail', 'detail' => 'No backup found.', 'fix' => 'Create one now on the Backups panel below'],
            $latest['created']->lt(now()->subDays(2)) => ['label' => 'Backups', 'status' => 'fail', 'detail' => 'Last backup '.$latest['created']->diffForHumans().'.',
                'fix' => 'Check the scheduler, or run a backup now'],
            default => ['label' => 'Backups', 'status' => 'ok', 'detail' => 'Last backup '.$latest['created']->diffForHumans()],
        };

        $provider = setting('sms_provider', 'none') ?: 'none';
        $failed = SmsMessage::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $checks[] = match (true) {
            $provider === 'none' => ['label' => 'SMS', 'status' => 'warn', 'detail' => 'No provider — messages are logged but not sent.',
                'fix' => 'Hospital Settings → SMS messaging (optional)'],
            $failed > 0 => ['label' => 'SMS', 'status' => 'warn', 'detail' => "{$failed} message(s) failed in the last 24 hours.",
                'fix' => 'See SMS Messages for the error (often low credit or a wrong key)'],
            default => ['label' => 'SMS', 'status' => 'ok', 'detail' => SmsService::PROVIDERS[$provider] ?? $provider],
        };

        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $gb = round($free / 1024 ** 3, 1);
            $checks[] = ['label' => 'Disk space', 'status' => $gb < 2 ? 'fail' : ($gb < 10 ? 'warn' : 'ok'), 'detail' => "{$gb} GB free"];
        }

        $checks[] = is_writable(storage_path())
            ? ['label' => 'Storage folder', 'status' => 'ok', 'detail' => 'Writable']
            : ['label' => 'Storage folder', 'status' => 'fail', 'detail' => 'Not writable', 'fix' => 'Give the web server write access to storage/'];

        try {
            Artisan::call('migrate:status', ['--pending' => true]);
            $pending = str_contains(Artisan::output(), 'Pending');
            $checks[] = $pending
                ? ['label' => 'Database updates', 'status' => 'fail', 'detail' => 'Pending migrations', 'fix' => 'Run `php artisan migrate --force`']
                : ['label' => 'Database updates', 'status' => 'ok', 'detail' => 'Up to date'];
        } catch (Throwable) {
            // migrate:status unavailable; skip.
        }

        $checks[] = ['label' => 'PHP', 'status' => version_compare(PHP_VERSION, '8.2', '>=') ? 'ok' : 'fail', 'detail' => PHP_VERSION];

        return $checks;
    }
}
