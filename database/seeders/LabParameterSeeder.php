<?php

namespace Database\Seeders;

use App\Models\LabTest;
use Illuminate\Database\Seeder;

/**
 * Result fields and adult reference ranges for the starter lab tests.
 * Only fills tests that have no parameters yet. Ranges are typical
 * values; each laboratory should review them against its own methods.
 */
class LabParameterSeeder extends Seeder
{
    public function run(): void
    {
        $posNeg = ['Negative', 'Positive'];
        $reactive = ['Non-reactive', 'Reactive'];
        $dip = ['Negative', 'Trace', '+', '++', '+++'];

        // code => [[name, unit, type, low, high, ref_text, options], ...]
        $definitions = [
            'FBC' => [
                ['Haemoglobin', 'g/dL', 'numeric', 12, 17],
                ['PCV / Haematocrit', '%', 'numeric', 36, 50],
                ['WBC', '×10⁹/L', 'numeric', 4, 11],
                ['Neutrophils', '%', 'numeric', 40, 75],
                ['Lymphocytes', '%', 'numeric', 20, 45],
                ['Monocytes', '%', 'numeric', 2, 10],
                ['Eosinophils', '%', 'numeric', 1, 6],
                ['Platelets', '×10⁹/L', 'numeric', 150, 400],
                ['MCV', 'fL', 'numeric', 80, 100],
            ],
            'PCV' => [['PCV', '%', 'numeric', 36, 50]],
            'ESR' => [['ESR', 'mm/hr', 'numeric', 0, 20]],
            'BGRP' => [
                ['ABO group', null, 'option', null, null, null, ['A', 'B', 'AB', 'O']],
                ['Rhesus (D)', null, 'option', null, null, null, ['Positive', 'Negative']],
            ],
            'GENO' => [['Genotype', null, 'option', null, null, null, ['AA', 'AS', 'AC', 'SS', 'SC', 'CC']]],
            'CLOT' => [
                ['Prothrombin time', 's', 'numeric', 11, 13.5],
                ['INR', null, 'numeric', 0.8, 1.2],
                ['APTT', 's', 'numeric', 25, 35],
            ],
            'MPS' => [['Malaria parasites', null, 'option', null, null, 'Not seen', ['Not seen', 'Scanty', '+', '++', '+++', '++++']]],
            'MRDT' => [['Malaria RDT', null, 'option', null, null, 'Negative', $posNeg]],
            'RBG' => [['Random blood glucose', 'mmol/L', 'numeric', 3.9, 7.8]],
            'FBG' => [['Fasting blood glucose', 'mmol/L', 'numeric', 3.9, 5.5]],
            'HBA1C' => [['HbA1c', '%', 'numeric', 4, 5.6]],
            'EUCR' => [
                ['Sodium', 'mmol/L', 'numeric', 135, 145],
                ['Potassium', 'mmol/L', 'numeric', 3.5, 5.1],
                ['Chloride', 'mmol/L', 'numeric', 98, 107],
                ['Bicarbonate', 'mmol/L', 'numeric', 22, 29],
                ['Urea', 'mmol/L', 'numeric', 2.5, 7.1],
                ['Creatinine', 'µmol/L', 'numeric', 53, 115],
            ],
            'LFT' => [
                ['Total bilirubin', 'µmol/L', 'numeric', 3, 21],
                ['Direct bilirubin', 'µmol/L', 'numeric', 0, 5],
                ['ALT', 'U/L', 'numeric', 7, 56],
                ['AST', 'U/L', 'numeric', 10, 40],
                ['ALP', 'U/L', 'numeric', 44, 147],
                ['Total protein', 'g/L', 'numeric', 60, 83],
                ['Albumin', 'g/L', 'numeric', 35, 50],
            ],
            'LIPID' => [
                ['Total cholesterol', 'mmol/L', 'numeric', null, 5.2],
                ['HDL cholesterol', 'mmol/L', 'numeric', 1.0, null],
                ['LDL cholesterol', 'mmol/L', 'numeric', null, 3.4],
                ['Triglycerides', 'mmol/L', 'numeric', null, 1.7],
            ],
            'UA' => [['Uric acid', 'µmol/L', 'numeric', 150, 420]],
            'TFT' => [
                ['TSH', 'mIU/L', 'numeric', 0.4, 4.0],
                ['Free T4', 'pmol/L', 'numeric', 12, 22],
                ['Free T3', 'pmol/L', 'numeric', 3.1, 6.8],
            ],
            'PSA' => [['Total PSA', 'ng/mL', 'numeric', null, 4]],
            'TROP' => [['Troponin I', 'ng/L', 'numeric', null, 26]],
            'CRP' => [['C-reactive protein', 'mg/L', 'numeric', null, 5]],
            'URINE' => [
                ['Appearance', null, 'option', null, null, 'Clear', ['Clear', 'Cloudy', 'Turbid', 'Bloody']],
                ['pH', null, 'numeric', 4.5, 8],
                ['Specific gravity', null, 'numeric', 1.005, 1.030],
                ['Protein', null, 'option', null, null, 'Negative', $dip],
                ['Glucose', null, 'option', null, null, 'Negative', $dip],
                ['Ketones', null, 'option', null, null, 'Negative', $dip],
                ['Blood', null, 'option', null, null, 'Negative', $dip],
                ['Leukocytes', null, 'option', null, null, 'Negative', $dip],
                ['Nitrite', null, 'option', null, null, 'Negative', $posNeg],
            ],
            'WIDAL' => [
                ['S. typhi O', 'titre', 'option', null, null, null, ['<1:40', '1:40', '1:80', '1:160', '1:320']],
                ['S. typhi H', 'titre', 'option', null, null, null, ['<1:40', '1:40', '1:80', '1:160', '1:320']],
            ],
            'HIV' => [['HIV 1 & 2', null, 'option', null, null, 'Non-reactive', $reactive]],
            'HBSAG' => [['HBsAg', null, 'option', null, null, 'Negative', $posNeg]],
            'HCV' => [['Anti-HCV', null, 'option', null, null, 'Negative', $posNeg]],
            'VDRL' => [['VDRL / RPR', null, 'option', null, null, 'Non-reactive', $reactive]],
            'HPYL' => [['H. pylori', null, 'option', null, null, 'Negative', $posNeg]],
            'PREG' => [['Urine hCG', null, 'option', null, null, null, $posNeg]],
            'CD4' => [['CD4 count', 'cells/µL', 'numeric', 500, 1500]],
            'VL' => [['HIV-1 RNA', 'copies/mL', 'numeric', null, 50]],
            'SPUTAFB' => [['AFB (Ziehl-Neelsen)', null, 'option', null, null, 'No AFB seen', ['No AFB seen', 'Scanty', '1+', '2+', '3+']]],
            'GXPERT' => [
                ['MTB', null, 'option', null, null, 'Not detected', ['Not detected', 'Detected', 'Invalid']],
                ['Rifampicin resistance', null, 'option', null, null, null, ['Not detected', 'Detected', 'Indeterminate', 'N/A']],
            ],
        ];

        $tests = LabTest::whereIn('code', array_keys($definitions))->doesntHave('parameters')->get();

        foreach ($tests as $test) {
            foreach ($definitions[$test->code] as $i => $row) {
                [$name, $unit, $type, $low, $high, $refText, $options] = array_pad($row, 7, null);
                $test->parameters()->create([
                    'name' => $name, 'unit' => $unit, 'type' => $type, 'ref_low' => $low, 'ref_high' => $high,
                    'ref_text' => $refText, 'options' => $options, 'sort_order' => $i,
                ]);
            }
        }
    }
}
