<?php

namespace Database\Seeders;

use App\Models\SurgicalProcedure;
use App\Models\Theatre;
use Illuminate\Database\Seeder;

/**
 * One starter theatre and common procedures. Only seeds empty tables;
 * everything is editable under Clinical Catalogues.
 */
class TheatreSeeder extends Seeder
{
    public function run(): void
    {
        if (Theatre::query()->doesntExist()) {
            Theatre::create(['code' => 'MT1', 'name' => 'Main Theatre 1']);
        }

        if (SurgicalProcedure::query()->exists()) {
            return;
        }

        // [code, name, specialty, typical minutes]
        foreach ([
            ['CS', 'Caesarean section', 'Obstetrics', 60],
            ['EVAC', 'Evacuation of retained products (MVA / D&C)', 'Gynaecology', 30],
            ['MYOM', 'Myomectomy', 'Gynaecology', 120],
            ['TAH', 'Total abdominal hysterectomy', 'Gynaecology', 120],
            ['ECTOPIC', 'Laparotomy for ectopic pregnancy', 'Gynaecology', 60],
            ['BTL', 'Bilateral tubal ligation', 'Gynaecology', 30],
            ['APPX', 'Appendicectomy', 'General surgery', 60],
            ['HERNIA', 'Inguinal herniorrhaphy', 'General surgery', 60],
            ['LAPAROTOMY', 'Exploratory laparotomy', 'General surgery', 120],
            ['CHOLE', 'Cholecystectomy', 'General surgery', 90],
            ['THYROID', 'Thyroidectomy', 'General surgery', 150],
            ['MASTECT', 'Mastectomy', 'General surgery', 120],
            ['LUMP', 'Excision of lump / biopsy', 'General surgery', 30],
            ['I&D', 'Incision and drainage of abscess', 'General surgery', 20],
            ['WOUND', 'Wound debridement', 'General surgery', 30],
            ['HAEM', 'Haemorrhoidectomy', 'General surgery', 45],
            ['CIRC', 'Circumcision', 'General surgery', 30],
            ['PROST', 'Prostatectomy', 'Urology', 120],
            ['HYDRO', 'Hydrocelectomy', 'Urology', 45],
            ['ORIF', 'Open reduction & internal fixation', 'Orthopaedics', 120],
            ['MUA', 'Manipulation under anaesthesia / POP', 'Orthopaedics', 30],
            ['AMPUT', 'Amputation', 'Orthopaedics', 90],
            ['CATARACT', 'Cataract extraction with IOL', 'Ophthalmology', 45],
            ['TONSIL', 'Tonsillectomy', 'ENT', 45],
            ['SKINGRAFT', 'Split-skin graft', 'Plastic surgery', 60],
        ] as [$code, $name, $specialty, $minutes]) {
            SurgicalProcedure::create(['code' => $code, 'name' => $name, 'specialty' => $specialty, 'typical_minutes' => $minutes]);
        }
    }
}
