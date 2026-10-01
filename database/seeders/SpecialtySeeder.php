<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Billable services for the dental, eye and physiotherapy clinics.
 * Unpriced services are never charged; set prices in Finance → Price List.
 */
class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            'Dental' => [
                'DEN-EXAM' => 'Dental examination',
                'DEN-XRAY' => 'Dental X-ray (periapical)',
                'DEN-SCALE' => 'Scaling & polishing',
                'DEN-EXT' => 'Tooth extraction (simple)',
                'DEN-SEXT' => 'Surgical extraction',
                'DEN-FILL-A' => 'Amalgam filling',
                'DEN-FILL-C' => 'Composite (tooth-coloured) filling',
                'DEN-RCT' => 'Root canal treatment',
                'DEN-CROWN' => 'Crown',
                'DEN-DENT' => 'Denture',
                'DEN-FLUOR' => 'Fluoride application',
                'DEN-SEAL' => 'Fissure sealant',
                'DEN-IND' => 'Incision & drainage (dental abscess)',
            ],
            'Eye' => [
                Service::EYE_REFRACTION => 'Refraction / eye test',
                'EYE-IOP' => 'Tonometry (eye pressure)',
                'EYE-FUNDUS' => 'Dilated fundus examination',
            ],
            'Physiotherapy' => [
                Service::PHYSIO_ASSESSMENT => 'Physiotherapy assessment',
                Service::PHYSIO_SESSION => 'Physiotherapy session',
            ],
        ];

        foreach ($services as $category => $list) {
            foreach ($list as $code => $name) {
                Service::firstOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'is_active' => true]);
            }
        }
    }
}
