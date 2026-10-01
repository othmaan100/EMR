<?php

namespace App\Imports;

use App\Models\SurgicalProcedure;

class ProcedureImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'procedures';
    }

    public function title(): string
    {
        return 'Surgical procedures';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Operations that can be booked in theatre.';
    }

    protected function model(): string
    {
        return SurgicalProcedure::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'HERN', null, null, 'APPX'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Inguinal herniorrhaphy', null, null, 'Appendicectomy'),
            new ImportColumn('specialty', 'Specialty', true, ['string', 'max:50'], 'General Surgery', null, null, 'General Surgery'),
            new ImportColumn('typical_minutes', 'Typical duration (min)', false, ['integer', 'min:5', 'max:1440'], '90', null, null, '60'),
            self::activeColumn(),
        ];
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'specialty' => 'specialty', 'typical_minutes' => 'typical_minutes', 'is_active' => 'is_active'];
    }
}
