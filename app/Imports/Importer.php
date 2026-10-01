<?php

namespace App\Imports;

use App\Models\User;

/**
 * Base class for one type of import. Subclasses declare the columns and
 * how a validated row is matched and saved; ImportService does the rest
 * (reading the file, validation, duplicates, preview, transaction).
 */
abstract class Importer
{
    /** Filled by ImportService before rows are processed. */
    public array $options = [];

    public ?User $user = null;

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function group(): string;

    abstract public function description(): string;

    /**
     * @return list<ImportColumn>
     */
    abstract public function columns(): array;

    /**
     * The existing record this row matches (for "update"), or null.
     */
    abstract public function find(array $row): mixed;

    /**
     * Save one validated row. $existing is what find() returned (null = create).
     */
    abstract public function save(array $row, mixed $existing): void;

    /**
     * Human description of how rows are matched to existing records.
     */
    abstract public function matchDescription(): string;

    /**
     * Extra instructions shown on the page and in the template.
     *
     * @return list<string>
     */
    public function notes(): array
    {
        return [];
    }

    /**
     * Imports that should be done first.
     *
     * @return list<string>  importer keys
     */
    public function dependsOn(): array
    {
        return [];
    }

    /**
     * Extra fields on the upload form: name => [label, type, rules, help].
     *
     * @return array<string, array{0: string, 1: string, 2: list<mixed>, 3: ?string}>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Turn submitted option values into what is stored with the import
     * (e.g. hash a password so it is never saved in plain text).
     */
    public static function prepareOptions(array $options): array
    {
        return $options;
    }

    /**
     * Whether matching rows can be updated (some imports only ever add).
     */
    public function supportsUpdate(): bool
    {
        return true;
    }

    /**
     * Tidy raw values before validation (trim, map "M" → "male", dates…).
     */
    public function normalise(array $row): array
    {
        return $row;
    }

    /**
     * Checks beyond the column rules (lookups that must exist, etc).
     * May add resolved values to $row (e.g. _department_id).
     *
     * @return list<string>  error messages
     */
    public function check(array &$row): array
    {
        return [];
    }

    /**
     * Identifies the row within the file so duplicates can be reported.
     */
    public function rowKey(array $row): ?string
    {
        return null;
    }

    /**
     * Called once after all rows are saved (inside the same transaction).
     */
    public function finish(): void {}

    /**
     * Permission needed in addition to data.import (null = none).
     */
    public function permission(): ?string
    {
        return null;
    }

    public function column(string $name): ?ImportColumn
    {
        foreach ($this->columns() as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }
}
