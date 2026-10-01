<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Hospital details and the first administrator are created through the
     * web setup wizard, so only reference data is seeded here.
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);
    }
}
