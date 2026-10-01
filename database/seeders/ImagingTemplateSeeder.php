<?php

namespace Database\Seeders;

use App\Models\ImagingTest;
use Illuminate\Database\Seeder;

/**
 * Normal-study report templates. Only fills procedures without one.
 */
class ImagingTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'XR-CHEST' => "The trachea is central. The cardiac size is within normal limits (CTR < 0.5).\n"
                ."Both lung fields are clear with no focal consolidation, mass or effusion.\n"
                ."The costophrenic angles are clear. The hemidiaphragms are normal.\n"
                .'The visualised bony thorax and soft tissues are unremarkable.',
            'XR-ABD' => "Normal bowel gas pattern with no dilated loops or air-fluid levels.\n"
                ."No free intraperitoneal air. No radio-opaque calculi seen.\n"
                .'The visualised bones are unremarkable.',
            'US-ABD' => "LIVER: Normal in size and echotexture. No focal lesion. No intrahepatic duct dilatation.\n"
                ."GALLBLADDER: Normally distended, thin-walled, no calculi.\n"
                ."PANCREAS: Normal where visualised.\n"
                ."SPLEEN: Normal in size and echotexture.\n"
                ."KIDNEYS: Both normal in size and echotexture, with good corticomedullary differentiation. No calculi or hydronephrosis.\n"
                .'No free peritoneal fluid.',
            'US-PELVIC' => "UTERUS: Anteverted, normal in size and echotexture. Endometrium is central and of normal thickness.\n"
                ."OVARIES: Both normal in size and appearance.\n"
                ."ADNEXA: No mass.\n"
                ."POUCH OF DOUGLAS: No free fluid.\n"
                .'BLADDER: Adequately filled, normal wall.',
            'US-OBS' => "Single live intrauterine fetus.\n"
                ."Presentation: \nFetal heart rate:  bpm\n"
                ."Biometry: BPD  mm, HC  mm, AC  mm, FL  mm\n"
                ."Estimated gestational age:  weeks  days (EDD: )\n"
                ."Estimated fetal weight:  g\n"
                ."Placenta:  , grade  , clear of the internal os.\n"
                .'Amniotic fluid: adequate (AFI  cm).',
            'US-PROST' => "Prostate measures  x  x  mm (volume  ml), normal echotexture.\n"
                .'Bladder adequately filled with normal wall. Post-void residual:  ml.',
            'ECG' => "Rhythm: sinus. Rate:  bpm. PR:  ms. QRS:  ms. QTc:  ms.\n"
                ."Axis: normal.\n"
                .'No ST-segment or T-wave abnormality.',
        ];

        foreach (ImagingTest::whereIn('code', array_keys($templates))->whereNull('report_template')->get() as $test) {
            $test->update(['report_template' => $templates[$test->code]]);
        }
    }
}
