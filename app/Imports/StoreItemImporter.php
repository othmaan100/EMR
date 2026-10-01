<?php

namespace App\Imports;

use App\Models\StoreItem;
use App\Services\StoresService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class StoreItemImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'store-items';
    }

    public function title(): string
    {
        return 'General store items & opening stock';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Non-drug items (consumables, reagents, linen, stationery…), optionally with the quantity currently in the store.';
    }

    public function notes(): array
    {
        return ['"opening_quantity" is added only when the item is created, so re-importing the file never double-counts stock. Use a stock-count adjustment to correct existing items.'];
    }

    protected function model(): string
    {
        return StoreItem::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'GLV-M', null, null, 'A4-PAPER'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Examination gloves (M)', null, null, 'A4 paper'),
            new ImportColumn('category', 'Category', true, [Rule::in(StoreItem::CATEGORIES)], 'Medical consumables', null, StoreItem::CATEGORIES, 'Stationery'),
            new ImportColumn('unit', 'Unit of issue', true, ['string', 'max:30'], 'box', null, null, 'ream'),
            new ImportColumn('reorder_level', 'Reorder level', false, ['integer', 'min:0', 'max:1000000'], '10', null, null, '5'),
            new ImportColumn('opening_quantity', 'Opening quantity', false, ['integer', 'min:0', 'max:10000000'], '40', 'Quantity in the store now (new items only).', null, '25'),
            new ImportColumn('unit_cost', 'Unit cost', false, ['numeric', 'min:0'], '1500', 'Cost per unit, for stock valuation.', null, '4200'),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['category'] = Values::option($row['category'], StoreItem::CATEGORIES);
        $row['unit_cost'] = Values::number($row['unit_cost']);
        $row['opening_quantity'] = Values::number($row['opening_quantity']);

        return $row;
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'category' => 'category', 'unit' => 'unit', 'reorder_level' => 'reorder_level', 'is_active' => 'is_active'];
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void
    {
        $model->reorder_level ??= 0;
    }

    protected function afterSave(Model $model, array $row, bool $updating): void
    {
        if (! $updating && (int) $row['opening_quantity'] > 0) {
            app(StoresService::class)->receive($model, (int) $row['opening_quantity'],
                $row['unit_cost'] !== null ? (float) $row['unit_cost'] : null, $this->user, null, 'Opening balance (data import)');
        }
    }
}
