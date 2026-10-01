<?php

namespace App\Console\Commands;

use App\Services\InpatientService;
use Illuminate\Console\Command;

class ChargeBeds extends Command
{
    protected $signature = 'emr:charge-beds';

    protected $description = 'Post daily bed charges for all current admissions (runs nightly)';

    public function handle(InpatientService $inpatients): int
    {
        $days = $inpatients->chargeAllBeds();
        $this->info("Posted {$days} bed-day charge(s).");

        return self::SUCCESS;
    }
}
