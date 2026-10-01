<?php

namespace App\Services;

use App\Imports\Importer;
use App\Imports\ImportRegistry;
use App\Imports\SpreadsheetFile;
use App\Models\DataImport;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs imports in two steps: analyse (validate every row, nothing saved)
 * then run (save the valid rows in one transaction).
 */
class ImportService
{
    public const DISK = 'local';

    public const MAX_STORED_ERRORS = 2000;

    /**
     * Store the uploaded file and check every row. Nothing is saved yet.
     */
    public function analyse(Importer $importer, string $sourcePath, string $originalName, string $mode, array $options, User $by): DataImport
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'xlsx' ? 'xlsx' : 'csv';
        $stored = 'imports/'.Str::random(40).'.'.$extension;
        Storage::disk(self::DISK)->put($stored, file_get_contents($sourcePath));

        $import = new DataImport([
            'type' => $importer->key(),
            'original_name' => Str::limit($originalName, 250, ''),
            'stored_path' => $stored,
            'mode' => $importer->supportsUpdate() ? $mode : 'create',
            'options' => $options,
            'status' => 'validated',
            'user_id' => $by->id,
        ]);

        try {
            $file = SpreadsheetFile::read(Storage::disk(self::DISK)->path($stored), $extension);
        } catch (Throwable $e) {
            return $this->fail($import, 'The file could not be read: '.$e->getMessage());
        }

        $expected = array_map(fn ($c) => $c->name, $importer->columns());
        $missing = array_values(array_filter($importer->columns(), fn ($c) => $c->required && ! in_array($c->name, $file['headers'], true)));
        if ($missing) {
            return $this->fail($import, 'Required column(s) missing: '.implode(', ', array_map(fn ($c) => $c->name, $missing))
                .'. Download the template and keep its headings.');
        }

        $unknown = array_values(array_diff($file['headers'], $expected));
        $import->warnings = $unknown ? ['Ignored column(s) not in the template: '.implode(', ', $unknown).'.'] : null;

        $this->prepare($importer, $import, $by);
        $tally = ['create' => 0, 'update' => 0, 'skip' => 0];
        $errors = [];
        $seen = [];

        foreach ($file['rows'] as $number => $raw) {
            [$action, $row, , $messages] = $this->evaluate($importer, $raw, $import->mode, $seen, $number);
            if ($messages) {
                $errors[] = ['row' => $number, 'messages' => $messages, 'data' => $this->plain($raw)];

                continue;
            }
            $tally[$action]++;
        }

        $import->fill([
            'total_rows' => count($file['rows']),
            'create_rows' => $tally['create'],
            'update_rows' => $tally['update'],
            'skip_rows' => $tally['skip'],
            'error_rows' => count($errors),
            'errors' => $this->cap($errors),
        ]);
        if ($import->total_rows === 0) {
            return $this->fail($import, 'The file has headings but no data rows.');
        }
        $import->save();

        return $import;
    }

    /**
     * Save every valid row. Rows are re-checked, because data may have
     * changed since the preview.
     */
    public function run(DataImport $import, User $by): DataImport
    {
        abort_unless($import->status === 'validated', 422, 'This import has already been run or cancelled.');
        $importer = ImportRegistry::make($import->type);
        @set_time_limit(0);

        $path = Storage::disk(self::DISK)->path($import->stored_path);
        $file = SpreadsheetFile::read($path, pathinfo($path, PATHINFO_EXTENSION));
        $this->prepare($importer, $import, $by);

        $counts = ['create' => 0, 'update' => 0, 'skip' => 0];
        $errors = [];

        Audit::muted(function () use ($importer, $file, $import, &$counts, &$errors) {
            DB::transaction(function () use ($importer, $file, $import, &$counts, &$errors) {
                $seen = [];
                foreach ($file['rows'] as $number => $raw) {
                    [$action, $row, $existing, $messages] = $this->evaluate($importer, $raw, $import->mode, $seen, $number);
                    if ($messages) {
                        $errors[] = ['row' => $number, 'messages' => $messages, 'data' => $this->plain($raw)];

                        continue;
                    }
                    if ($action === 'skip') {
                        $counts['skip']++;

                        continue;
                    }

                    try {
                        // Savepoint per row: a failure undoes only that row.
                        DB::transaction(fn () => $importer->save($row, $existing));
                        $counts[$action]++;
                    } catch (Throwable $e) {
                        report($e);
                        $errors[] = ['row' => $number, 'messages' => ['Could not be saved: '.Str::limit($e->getMessage(), 200)], 'data' => $this->plain($raw)];
                    }
                }

                $importer->finish();
            });
        });

        $import->forceFill([
            'status' => 'completed',
            'created_count' => $counts['create'],
            'updated_count' => $counts['update'],
            'skipped_count' => $counts['skip'],
            'error_rows' => count($errors),
            'errors' => $this->cap($errors),
            'completed_at' => now(),
        ])->save();
        $this->discardFile($import);

        Audit::log('data_imported', "{$importer->title()} import \"{$import->original_name}\": {$counts['create']} added, {$counts['update']} updated, "
            .$counts['skip'].' skipped, '.count($errors).' with errors', $import);

        return $import;
    }

    public function cancel(DataImport $import): void
    {
        if ($import->status === 'validated') {
            $this->discardFile($import);
            $import->forceFill(['status' => 'cancelled'])->save();
        }
    }

    /**
     * Rows that failed, with an "errors" column, ready to fix and re-upload.
     */
    public function errorReport(DataImport $import): string
    {
        $importer = ImportRegistry::make($import->type);
        $columns = array_map(fn ($c) => $c->name, $importer->columns());

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [...$columns, 'row_in_original_file', 'errors']);
        foreach ($import->errors ?? [] as $error) {
            fputcsv($out, [...array_map(fn ($c) => $error['data'][$c] ?? '', $columns), $error['row'], implode(' | ', $error['messages'])]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @return array{0: string, 1: array, 2: mixed, 3: list<string>}  action, row, existing, errors
     */
    protected function evaluate(Importer $importer, array $raw, string $mode, array &$seen, int $number): array
    {
        $row = [];
        foreach ($importer->columns() as $column) {
            $value = $raw[$column->name] ?? null;
            $row[$column->name] = $value instanceof \DateTimeInterface ? $value : (is_string($value) ? trim($value) : $value);
        }
        $row = $importer->normalise($row);

        $rules = [];
        $attributes = [];
        foreach ($importer->columns() as $column) {
            $rules[$column->name] = $column->validationRules();
            $attributes[$column->name] = $column->name;
        }
        $validator = Validator::make($row, $rules, [
            'date' => 'The :attribute must be a date, e.g. 2024-01-31 or 31/01/2024.',
            'date_format' => 'The :attribute must be a time like 09:30.',
            'in' => 'The :attribute value is not one of the allowed values (see the template instructions).',
        ], $attributes);

        $messages = $validator->errors()->all();
        if (! $messages) {
            $messages = $importer->check($row);
        }
        if (! $messages && ($key = $importer->rowKey($row)) !== null) {
            $key = mb_strtolower($key);
            if (isset($seen[$key])) {
                $messages[] = "Same record as row {$seen[$key]} of this file.";
            } else {
                $seen[$key] = $number;
            }
        }
        if ($messages) {
            return ['error', $row, null, $messages];
        }

        $existing = $importer->find($row);
        $action = $existing === null ? 'create' : ($mode === 'update' && $importer->supportsUpdate() ? 'update' : 'skip');

        return [$action, $row, $existing, []];
    }

    protected function prepare(Importer $importer, DataImport $import, User $by): void
    {
        $importer->options = $import->options ?? [];
        $importer->user = $by;
    }

    protected function fail(DataImport $import, string $message): DataImport
    {
        $import->forceFill(['status' => 'failed', 'message' => Str::limit($message, 250)])->save();
        $this->discardFile($import);

        return $import;
    }

    protected function discardFile(DataImport $import): void
    {
        if ($import->stored_path) {
            Storage::disk(self::DISK)->delete($import->stored_path);
            $import->forceFill(['stored_path' => null])->save();
        }
    }

    protected function plain(array $raw): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $raw);
    }

    protected function cap(array $errors): ?array
    {
        return $errors ? array_slice($errors, 0, self::MAX_STORED_ERRORS) : null;
    }
}
