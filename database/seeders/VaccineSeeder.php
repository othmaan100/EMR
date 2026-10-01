<?php

namespace Database\Seeders;

use App\Models\Vaccine;
use Illuminate\Database\Seeder;

/**
 * WHO-recommended routine childhood (EPI) schedule. Countries differ, so the
 * schedule is fully editable under Clinical Catalogues → Vaccines.
 * Only seeds when the table is empty.
 */
class VaccineSeeder extends Seeder
{
    public function run(): void
    {
        if (Vaccine::query()->exists()) {
            return;
        }

        // [code, name, dose label, age in days, route]
        $schedule = [
            ['BCG', 'BCG', 'Birth', 0, 'Intradermal'],
            ['OPV0', 'Oral polio (OPV)', 'Birth dose', 0, 'Oral'],
            ['HEPB0', 'Hepatitis B', 'Birth dose', 0, 'IM'],
            ['OPV1', 'Oral polio (OPV)', 'Dose 1', 42, 'Oral'],
            ['PENTA1', 'Pentavalent (DTP-HepB-Hib)', 'Dose 1', 42, 'IM'],
            ['PCV1', 'Pneumococcal (PCV)', 'Dose 1', 42, 'IM'],
            ['ROTA1', 'Rotavirus', 'Dose 1', 42, 'Oral'],
            ['OPV2', 'Oral polio (OPV)', 'Dose 2', 70, 'Oral'],
            ['PENTA2', 'Pentavalent (DTP-HepB-Hib)', 'Dose 2', 70, 'IM'],
            ['PCV2', 'Pneumococcal (PCV)', 'Dose 2', 70, 'IM'],
            ['ROTA2', 'Rotavirus', 'Dose 2', 70, 'Oral'],
            ['OPV3', 'Oral polio (OPV)', 'Dose 3', 98, 'Oral'],
            ['PENTA3', 'Pentavalent (DTP-HepB-Hib)', 'Dose 3', 98, 'IM'],
            ['PCV3', 'Pneumococcal (PCV)', 'Dose 3', 98, 'IM'],
            ['IPV1', 'Inactivated polio (IPV)', 'Dose 1', 98, 'IM'],
            ['MR1', 'Measles-rubella (MR)', 'Dose 1', 274, 'SC'],
            ['YF', 'Yellow fever', 'Single dose', 274, 'SC'],
            ['MR2', 'Measles-rubella (MR)', 'Dose 2', 456, 'SC'],
        ];

        foreach ($schedule as $i => [$code, $name, $dose, $age, $route]) {
            Vaccine::create(['code' => $code, 'name' => $name, 'dose_label' => $dose, 'age_days' => $age, 'route' => $route, 'sort_order' => $i]);
        }
    }
}
