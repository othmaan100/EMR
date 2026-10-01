<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Load ICD-10 codes from a CSV of "code,description" rows (header optional).
 * Use it to replace the starter list with a full national/WHO code set.
 */
class ImportIcd10 extends Command
{
    protected $signature = 'emr:import-icd10 {file : Path to CSV (code,description)} {--fresh : Delete existing codes first}';

    protected $description = 'Import ICD-10 diagnosis codes from a CSV file';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_readable($path) || ! ($handle = fopen($path, 'r'))) {
            $this->error("Cannot read $path");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('icd10_codes')->delete();
        }

        $batch = [];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            [$code, $description] = array_pad(array_map('trim', $row), 2, '');
            if ($code === '' || $description === '' || strcasecmp($code, 'code') === 0) {
                continue;
            }

            $batch[] = ['code' => mb_substr(strtoupper($code), 0, 10), 'description' => mb_substr($description, 0, 255)];
            if (count($batch) === 500) {
                $count += $this->flush($batch);
            }
        }
        $count += $this->flush($batch);
        fclose($handle);

        $this->info("Imported $count ICD-10 codes.");

        return self::SUCCESS;
    }

    protected function flush(array &$batch): int
    {
        if (! $batch) {
            return 0;
        }

        DB::table('icd10_codes')->upsert($batch, ['code'], ['description']);
        $n = count($batch);
        $batch = [];

        return $n;
    }
}
