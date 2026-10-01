<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use Illuminate\Database\Eloquent\Model;

/**
 * Simple "code + fields" catalogues (departments, services, lab tests…).
 * Rows are matched on the code column.
 */
abstract class CatalogImporter extends Importer
{
    use Lookups;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /**
     * Column name → model attribute, for plain copied fields.
     *
     * @return array<string, string>
     */
    abstract protected function fields(): array;

    protected function keyColumn(): string
    {
        return 'code';
    }

    public function matchDescription(): string
    {
        return "Rows are matched to existing records by \"{$this->keyColumn()}\".";
    }

    public function rowKey(array $row): ?string
    {
        return (string) $row[$this->keyColumn()];
    }

    public function normalise(array $row): array
    {
        if ($this->keyColumn() === 'code' && isset($row['code'])) {
            $row['code'] = Values::upper($row['code']);
        }
        if (array_key_exists('phone', $row)) {
            $row['phone'] = Values::phone($row['phone']);
        }
        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = Values::bool($row['is_active']);
        }

        return $row;
    }

    public function find(array $row): mixed
    {
        $model = $this->model();

        return $model::query()->whereRaw('lower('.$this->keyColumn().') = ?', [mb_strtolower((string) $row[$this->keyColumn()])])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $model = $existing ?? new ($this->model());
        foreach ($this->fields() as $column => $attribute) {
            // On update, blank cells leave the current value alone.
            if ($existing && ($row[$column] ?? null) === null) {
                continue;
            }
            $model->{$attribute} = $row[$column] ?? null;
        }
        if (! $existing && isset($this->fields()['is_active']) && $model->is_active === null) {
            $model->is_active = true; // new records are active unless the file says otherwise
        }
        $this->beforeSave($model, $row, $existing !== null);
        $model->save();
        $this->afterSave($model, $row, $existing !== null);
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void {}

    protected function afterSave(Model $model, array $row, bool $updating): void {}

    protected static function activeColumn(): ImportColumn
    {
        return new ImportColumn('is_active', 'Active', false, ['in:0,1'], 'yes', 'yes or no (default yes).', ['yes', 'no']);
    }
}
