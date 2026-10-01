<?php

namespace App\Support;

use App\Models\Patient;
use Illuminate\Support\Str;

/**
 * Compares a drug against the patient's free-text allergy list, including
 * common drug-class cross-reactions (e.g. penicillin → amoxicillin).
 * A safety net only: it cannot catch every interaction.
 */
class AllergyChecker
{
    /** allergy keyword => drug name fragments in that class */
    protected const CLASSES = [
        'penicillin' => ['penicillin', 'amoxicillin', 'ampicillin', 'cloxacillin', 'flucloxacillin', 'piperacillin', 'benzathine'],
        'cephalosporin' => ['ceftriaxone', 'cefuroxime', 'cefixime', 'cefalexin', 'cephalexin', 'ceftazidime', 'cefotaxime'],
        'sulfa' => ['cotrimoxazole', 'sulfamethoxazole', 'sulfadoxine', 'sulfasalazine'],
        'sulph' => ['cotrimoxazole', 'sulfamethoxazole', 'sulfadoxine', 'sulfasalazine'],
        'nsaid' => ['ibuprofen', 'diclofenac', 'aspirin', 'naproxen', 'piroxicam', 'indomethacin', 'ketorolac'],
        'aspirin' => ['aspirin', 'ibuprofen', 'diclofenac', 'naproxen'],
        'quinolone' => ['ciprofloxacin', 'levofloxacin', 'ofloxacin', 'moxifloxacin'],
        'macrolide' => ['azithromycin', 'erythromycin', 'clarithromycin'],
        'opioid' => ['morphine', 'tramadol', 'codeine', 'pethidine', 'pentazocine'],
    ];

    /**
     * @return list<string> the allergy entries that conflict with this drug
     */
    public static function conflicts(Patient $patient, string $drugName): array
    {
        if (blank($patient->allergies)) {
            return [];
        }

        $drug = Str::lower($drugName);
        $conflicts = [];

        foreach (preg_split('/[,;\n\/]+/', Str::lower($patient->allergies)) as $allergy) {
            $allergy = trim($allergy);
            if (mb_strlen($allergy) < 3 || in_array($allergy, ['none', 'nil', 'nkda', 'no known allergies'], true)) {
                continue;
            }

            // Direct name match either way ("amoxicillin" in "Amoxicillin 500mg").
            $firstWord = Str::before($drug, ' ');
            $hit = Str::contains($drug, $allergy) || Str::contains($allergy, $firstWord);

            foreach (self::CLASSES as $keyword => $members) {
                if (Str::contains($allergy, $keyword) && Str::contains($drug, $members)) {
                    $hit = true;
                }
            }

            if ($hit) {
                $conflicts[] = $allergy;
            }
        }

        return array_values(array_unique($conflicts));
    }
}
