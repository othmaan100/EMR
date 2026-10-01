<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class SendSms extends Command
{
    protected $signature = 'emr:send-sms';

    protected $description = 'Send queued SMS messages (runs every minute)';

    public function handle(SmsService $sms): int
    {
        $this->info('Delivered '.$sms->flush().' message(s).');

        return self::SUCCESS;
    }
}
