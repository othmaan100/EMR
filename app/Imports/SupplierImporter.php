<?php

namespace App\Imports;

use App\Models\Supplier;

class SupplierImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function title(): string
    {
        return 'Suppliers';
    }

    public function group(): string
    {
        return 'Organisation';
    }

    public function description(): string
    {
        return 'Companies that supply drugs and store items.';
    }

    protected function model(): string
    {
        return Supplier::class;
    }

    protected function keyColumn(): string
    {
        return 'name';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Medlink Supplies Ltd', null, null, 'Emzor Pharmaceuticals'),
            new ImportColumn('contact_person', 'Contact person', false, ['string', 'max:150'], 'Mr Bello'),
            new ImportColumn('phone', 'Phone', false, ['string', 'max:30'], '08031234567'),
            new ImportColumn('email', 'Email', false, ['email', 'max:150']),
            new ImportColumn('address', 'Address', false, ['string', 'max:255']),
            self::activeColumn(),
        ];
    }

    protected function fields(): array
    {
        return ['name' => 'name', 'contact_person' => 'contact_person', 'phone' => 'phone', 'email' => 'email', 'address' => 'address', 'is_active' => 'is_active'];
    }
}
