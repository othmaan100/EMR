<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * System services that automatic charges look for. Prices are left for
 * each hospital to set (no price = not charged).
 */
class BillingSeeder extends Seeder
{
    public function run(): void
    {
        Service::firstOrCreate(['code' => Service::REGISTRATION], ['name' => 'Registration / card fee', 'category' => 'Registration']);
        Service::firstOrCreate(['code' => Service::CONSULTATION], ['name' => 'Consultation fee', 'category' => 'Consultation']);
        Service::firstOrCreate(['code' => Service::ANC_BOOKING], ['name' => 'Antenatal booking', 'category' => 'Maternity']);
        Service::firstOrCreate(['code' => Service::DELIVERY_VAGINAL], ['name' => 'Vaginal delivery', 'category' => 'Maternity']);
        Service::firstOrCreate(['code' => Service::DELIVERY_CAESAREAN], ['name' => 'Caesarean section', 'category' => 'Maternity']);
        Service::firstOrCreate(['code' => Service::THEATRE_FEE], ['name' => 'Theatre fee', 'category' => 'Procedure']);
    }
}
