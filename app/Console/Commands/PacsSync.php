<?php

namespace App\Console\Commands;

use App\Integrations\Pacs\PacsService;
use App\Support\SystemUser;
use Illuminate\Console\Command;

class PacsSync extends Command
{
    protected $signature = 'emr:pacs-sync';

    protected $description = 'Link imaging orders to studies that have arrived in the PACS (runs every 5 minutes)';

    public function handle(PacsService $pacs): int
    {
        if (! $pacs->enabled()) {
            $this->line('PACS link is not switched on.');

            return self::SUCCESS;
        }

        // Orders marked performed by the sync show "PACS link" as the performer.
        $this->info('Linked '.$pacs->sync(SystemUser::for('pacs', 'PACS link')).' order(s) to PACS studies.');

        return self::SUCCESS;
    }
}
