<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\StockBatch;
use App\Services\PharmacyService;

class DrugStockImporter extends Importer
{
    use Lookups;

    /** @var array<string, array{supplier_id: ?int, lines: list<array>}> */
    protected array $receipts = [];

    public function key(): string
    {
        return 'drug-stock';
    }

    public function title(): string
    {
        return 'Drug stock (opening balances)';
    }

    public function group(): string
    {
        return 'Opening balances & history';
    }

    public function description(): string
    {
        return 'Drugs physically in the pharmacy on go-live day, by batch and expiry date.';
    }

    public function dependsOn(): array
    {
        return ['drugs', 'suppliers'];
    }

    public function notes(): array
    {
        return [
            'One row per batch. Count the shelves on go-live day and import once.',
            'Expired batches cannot be imported — dispose of them instead.',
            'A batch already in stock (same drug + batch number) is skipped, so the file can safely be re-uploaded after fixing errors.',
            'Each supplier in the file gets one goods-received note (GRN) marked "Opening stock".',
        ];
    }

    public function matchDescription(): string
    {
        return 'Batches already in stock (same drug + batch number) are skipped; stock is only ever added.';
    }

    public function supportsUpdate(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('drug', 'Drug (formulary name)', true, ['string', 'max:200'], 'Paracetamol 500mg Tablet', 'Full name as in the formulary, e.g. "Amoxicillin 500mg Capsule".', null, 'Amoxicillin 500mg Capsule'),
            new ImportColumn('batch_number', 'Batch number', true, ['string', 'max:50'], 'PCM2408', null, null, 'AMX-77'),
            new ImportColumn('expiry_date', 'Expiry date', true, ['date', 'after:today'], '2027-08-31', Values::DATE_HELP, null, '2026-11-30'),
            new ImportColumn('quantity', 'Quantity (stock units)', true, ['integer', 'min:1', 'max:10000000'], '1000', 'In the drug\'s stock unit (tablets, bottles…).', null, '300'),
            new ImportColumn('unit_cost', 'Unit cost', false, ['numeric', 'min:0'], '4.50', 'Cost price per stock unit.', null, '25'),
            new ImportColumn('supplier', 'Supplier name', false, ['string', 'max:150'], 'Medlink Supplies Ltd', 'Must match an imported supplier (optional).'),
        ];
    }

    public function normalise(array $row): array
    {
        $row['expiry_date'] = Values::date($row['expiry_date']);
        $row['quantity'] = Values::number($row['quantity']);
        $row['unit_cost'] = Values::number($row['unit_cost']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];
        $row['_drug_id'] = $this->drugId($row['drug']);
        if (! $row['_drug_id']) {
            $errors[] = "Drug \"{$row['drug']}\" is not in the formulary (use the full name, e.g. \"Paracetamol 500mg Tablet\").";
        }
        $row['_supplier_id'] = $this->lookup('supplier', $row['supplier']);
        if ($row['supplier'] && ! $row['_supplier_id']) {
            $errors[] = "Supplier \"{$row['supplier']}\" not found — import suppliers first, or leave blank.";
        }

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return $row['_drug_id'].'|'.mb_strtolower($row['batch_number']);
    }

    public function find(array $row): mixed
    {
        return StockBatch::where('drug_id', $row['_drug_id'])->whereRaw('lower(batch_number) = ?', [mb_strtolower($row['batch_number'])])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $key = (string) ($row['_supplier_id'] ?? 'none');
        $this->receipts[$key] ??= ['supplier_id' => $row['_supplier_id'], 'lines' => []];
        $this->receipts[$key]['lines'][] = [
            'drug_id' => $row['_drug_id'],
            'batch_number' => $row['batch_number'],
            'expiry_date' => $row['expiry_date'],
            'quantity' => (int) $row['quantity'],
            'unit_cost' => $row['unit_cost'] !== null ? (float) $row['unit_cost'] : null,
        ];
    }

    public function finish(): void
    {
        foreach ($this->receipts as $receipt) {
            app(PharmacyService::class)->receive([
                'supplier_id' => $receipt['supplier_id'],
                'invoice_number' => 'OPENING',
                'received_on' => today()->toDateString(),
                'notes' => 'Opening stock (data import)',
            ], $receipt['lines'], $this->user);
        }
    }
}
