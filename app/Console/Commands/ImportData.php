<?php

namespace App\Console\Commands;

use App\Imports\ImportRegistry;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Console\Command;

/**
 * Same imports as Administration → Data Import, for very large files.
 * Checks the file first; add --commit to save.
 */
class ImportData extends Command
{
    protected $signature = 'emr:import
        {type? : Import type, e.g. patients (omit to list types)}
        {file? : Path to the .xlsx or .csv file}
        {--update : Update records that already exist (default: skip them)}
        {--commit : Save the valid rows (without this, only checks the file)}
        {--user= : Username recorded as the importer (default: first Super Admin)}
        {--password= : Temporary password for new staff accounts without one}';

    protected $description = 'Import legacy data from a spreadsheet (check first, then --commit)';

    public function handle(ImportService $imports): int
    {
        $type = $this->argument('type');
        if (! $type || ! ImportRegistry::find($type)) {
            $this->table(['Type', 'Title', 'Group'], ImportRegistry::all()->map(fn ($i) => [$i->key(), $i->title(), $i->group()])->values()->all());

            return $type ? self::FAILURE : self::SUCCESS;
        }

        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::where('username', $this->option('user'))->first()
            : User::role(config('emr.super_admin_role'))->orderBy('id')->first();
        if (! $user) {
            $this->error('No user to record as the importer (use --user=username).');

            return self::FAILURE;
        }

        $importer = ImportRegistry::make($type);
        $options = $importer::prepareOptions(array_filter(['default_password' => $this->option('password')]));

        $this->info("Checking {$file} as \"{$importer->title()}\"…");
        $import = $imports->analyse($importer, $file, basename($file), $this->option('update') ? 'update' : 'create', $options, $user);

        if ($import->status === 'failed') {
            $this->error($import->message);

            return self::FAILURE;
        }

        $this->table(['Rows', 'Add', 'Update', 'Skip', 'Errors'], [[$import->total_rows, $import->create_rows, $import->update_rows, $import->skip_rows, $import->error_rows]]);
        foreach (array_slice($import->errors ?? [], 0, 20) as $error) {
            $this->line("  <fg=red>Row {$error['row']}:</> ".implode(' ', $error['messages']));
        }

        if (! $this->option('commit')) {
            $imports->cancel($import);
            $this->comment('Nothing saved. Run again with --commit to import the valid rows.');

            return self::SUCCESS;
        }

        $import = $imports->run($import, $user);
        $this->info("Imported: {$import->created_count} added, {$import->updated_count} updated, {$import->skipped_count} skipped, {$import->error_rows} errors.");
        $this->line('Details and error report: Administration → Data Import → history (import #'.$import->id.').');

        return self::SUCCESS;
    }
}
