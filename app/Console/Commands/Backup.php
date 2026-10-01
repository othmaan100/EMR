<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'emr:backup';

    protected $description = 'Back up the database and patient files to storage/app/backups (runs nightly)';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create();
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Created {$result['name']} ({$backups->human($result['size'])}, {$result['files']} files). Pruned {$result['pruned']} old backup(s).");

        return self::SUCCESS;
    }
}
