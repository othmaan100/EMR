<?php

namespace App\Imports;

use App\Http\Controllers\PriceListController;
use App\Imports\Concerns\Lookups;
use App\Models\Price;

class PriceImporter extends Importer
{
    use Lookups;

    public const ITEM_TYPES = ['service', 'lab-test', 'imaging', 'drug', 'procedure'];

    public function key(): string
    {
        return 'prices';
    }

    public function title(): string
    {
        return 'Prices (tariffs)';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Standard (self-pay) prices and HMO/company-specific tariffs for services, tests, drugs and procedures.';
    }

    public function dependsOn(): array
    {
        return ['services', 'lab-tests', 'imaging', 'drugs', 'procedures', 'insurers'];
    }

    public function notes(): array
    {
        return [
            'Leave payer_code blank for the standard price. Add one row per payer that has its own tariff.',
            'Drug prices are per stock unit (per tablet, per bottle…). For drugs, "item" is the full drug name as shown in the formulary, e.g. "Paracetamol 500mg Tablet".',
            'Items without a price are never charged.',
        ];
    }

    public function matchDescription(): string
    {
        return 'Rows are matched on item_type + item + payer_code; updating replaces that price.';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('item_type', 'Item type', true, ['in:'.implode(',', self::ITEM_TYPES)], 'service', null, self::ITEM_TYPES, 'drug'),
            new ImportColumn('item', 'Item code (drug: full name)', true, ['string', 'max:200'], 'CONSULT', 'Code of the service, test or procedure; for drugs the formulary name.', null, 'Paracetamol 500mg Tablet'),
            new ImportColumn('payer_code', 'Payer code', false, ['string', 'max:20'], '', 'Blank = standard price; otherwise the code of an HMO/company.', null, 'HYG'),
            new ImportColumn('amount', 'Amount', true, ['numeric', 'min:0', 'max:100000000'], '3000', 'Price in '.setting('currency_code', 'NGN').', numbers only.', null, '10'),
        ];
    }

    public function normalise(array $row): array
    {
        $row['item_type'] = Values::option($row['item_type'], self::ITEM_TYPES, [
            'services' => 'service', 'lab' => 'lab-test', 'lab test' => 'lab-test', 'lab_test' => 'lab-test', 'lab-tests' => 'lab-test', 'test' => 'lab-test',
            'radiology' => 'imaging', 'drugs' => 'drug', 'medicine' => 'drug', 'surgery' => 'procedure', 'procedures' => 'procedure',
        ]);
        $row['amount'] = Values::number($row['amount']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];
        $row['_provider_id'] = $this->lookup('insurer', $row['payer_code']);
        if ($row['payer_code'] && ! $row['_provider_id']) {
            $errors[] = "Payer \"{$row['payer_code']}\" not found — import insurers first.";
        }

        $row['_billable_type'] = $this->modelClass($row['item_type']);
        $row['_billable_id'] = $row['item_type'] === 'drug'
            ? $this->drugId($row['item'])
            : $row['_billable_type']::whereRaw('lower(code) = ?', [mb_strtolower($row['item'])])->value('id');
        if (! $row['_billable_id']) {
            $errors[] = "No {$row['item_type']} \"{$row['item']}\" in the catalogue.";
        }

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return "{$row['item_type']}|{$row['_billable_id']}|{$row['_provider_id']}";
    }

    public function find(array $row): mixed
    {
        return Price::where('billable_type', (new ($row['_billable_type']))->getMorphClass())->where('billable_id', $row['_billable_id'])
            ->where('insurance_provider_id', $row['_provider_id'])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $price = $existing ?? new Price([
            'billable_type' => (new ($row['_billable_type']))->getMorphClass(),
            'billable_id' => $row['_billable_id'],
            'insurance_provider_id' => $row['_provider_id'],
        ]);
        $price->amount = $row['amount'];
        $price->save();
    }

    protected function modelClass(string $type): string
    {
        return match ($type) {
            'service' => PriceListController::TYPES['services']['model'],
            'lab-test' => PriceListController::TYPES['lab-tests']['model'],
            'imaging' => PriceListController::TYPES['imaging']['model'],
            'drug' => PriceListController::TYPES['drugs']['model'],
            default => PriceListController::TYPES['procedures']['model'],
        };
    }
}
