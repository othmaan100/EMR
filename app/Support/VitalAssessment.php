<?php

namespace App\Support;

use App\Models\VitalSign;

/**
 * Flags abnormal vital signs and calculates the NEWS2 early-warning score.
 *
 * Adult reference ranges are used for patients aged 16+. For children only
 * universally applicable flags (temperature, SpO2) are raised, because
 * paediatric normals vary with age. NEWS2 is likewise adults-only.
 * These are decision aids; clinical judgement always prevails.
 */
class VitalAssessment
{
    public const LOW = 'low';

    public const HIGH = 'high';

    public const CRITICAL = 'critical';

    /**
     * field => [critical_low, low, high, critical_high] (null = no bound).
     */
    protected const ADULT_RANGES = [
        'temperature' => [35.0, 36.0, 37.5, 39.5],
        'systolic' => [80, 90, 140, 180],
        'diastolic' => [40, 60, 90, 120],
        'pulse' => [40, 60, 100, 130],
        'respiratory_rate' => [8, 12, 20, 30],
        'spo2' => [90, 95, null, null],
        'bmi' => [null, 18.5, 25, 40],
        'pain_score' => [null, null, 6, 8],
        'blood_glucose' => [3.0, 4.0, 11.0, 20.0],
    ];

    protected const ALL_AGES = ['temperature', 'spo2'];

    /**
     * @return array<string, string> field => low|high|critical
     */
    public static function flags(VitalSign $vitals, ?int $ageYears): array
    {
        $isAdult = $ageYears === null || $ageYears >= 16;
        $flags = [];

        foreach (self::ADULT_RANGES as $field => [$critLow, $low, $high, $critHigh]) {
            $value = $vitals->{$field};
            if ($value === null || (! $isAdult && ! in_array($field, self::ALL_AGES, true))) {
                continue;
            }

            $value = (float) $value;
            $flag = match (true) {
                $critLow !== null && $value <= $critLow, $critHigh !== null && $value >= $critHigh => self::CRITICAL,
                $low !== null && $value < $low => self::LOW,
                $high !== null && $value > $high => self::HIGH,
                default => null,
            };

            if ($flag) {
                $flags[$field] = $flag;
            }
        }

        if ($vitals->consciousness && $vitals->consciousness !== 'A') {
            $flags['consciousness'] = self::CRITICAL;
        }

        return $flags;
    }

    /**
     * NEWS2 (Royal College of Physicians, 2017), SpO2 scale 1.
     * Returns null unless all five numeric parameters are present.
     */
    public static function news2(VitalSign $v, ?int $ageYears): ?int
    {
        $parts = self::news2Components($v, $ageYears);

        return $parts === null ? null : array_sum($parts);
    }

    /**
     * Per-parameter NEWS2 scores, or null if not calculable.
     *
     * @return array<string, int>|null
     */
    public static function news2Components(VitalSign $v, ?int $ageYears): ?array
    {
        if (($ageYears !== null && $ageYears < 16)
            || in_array(null, [$v->respiratory_rate, $v->spo2, $v->systolic, $v->pulse, $v->temperature], true)) {
            return null;
        }

        $rr = (int) $v->respiratory_rate;
        $spo2 = (int) $v->spo2;
        $sbp = (int) $v->systolic;
        $hr = (int) $v->pulse;
        $temp = (float) $v->temperature;

        return [
            'respiratory_rate' => match (true) { $rr <= 8 => 3, $rr <= 11 => 1, $rr <= 20 => 0, $rr <= 24 => 2, default => 3 },
            'spo2' => match (true) { $spo2 <= 91 => 3, $spo2 <= 93 => 2, $spo2 <= 95 => 1, default => 0 },
            'on_oxygen' => $v->on_oxygen ? 2 : 0,
            'systolic' => match (true) { $sbp <= 90 => 3, $sbp <= 100 => 2, $sbp <= 110 => 1, $sbp <= 219 => 0, default => 3 },
            'pulse' => match (true) { $hr <= 40 => 3, $hr <= 50 => 1, $hr <= 90 => 0, $hr <= 110 => 1, $hr <= 130 => 2, default => 3 },
            'consciousness' => ($v->consciousness ?? 'A') === 'A' ? 0 : 3,
            'temperature' => match (true) { $temp <= 35.0 => 3, $temp <= 36.0 => 1, $temp <= 38.0 => 0, $temp <= 39.0 => 1, default => 2 },
        ];
    }

    /**
     * @return array{score: int, level: string, label: string, color: string, advice: string}|null
     */
    public static function news2Risk(VitalSign $v, ?int $ageYears): ?array
    {
        $parts = self::news2Components($v, $ageYears);
        if ($parts === null) {
            return null;
        }

        $score = array_sum($parts);
        $anySingleThree = in_array(3, $parts, true);

        return ['score' => $score] + match (true) {
            $score >= 7 => ['level' => 'high', 'label' => 'High', 'color' => 'danger', 'advice' => 'Emergency assessment by a clinical team; consider critical care.'],
            $score >= 5 => ['level' => 'medium', 'label' => 'Medium', 'color' => 'warning', 'advice' => 'Urgent review by a clinician.'],
            $anySingleThree => ['level' => 'low-medium', 'label' => 'Low–medium', 'color' => 'warning', 'advice' => 'A single parameter scores 3: urgent ward-based review.'],
            default => ['level' => 'low', 'label' => 'Low', 'color' => 'success', 'advice' => 'Continue routine monitoring.'],
        };
    }

    public static function bmi(?float $weightKg, ?float $heightCm): ?float
    {
        if (! $weightKg || ! $heightCm) {
            return null;
        }

        return round($weightKg / (($heightCm / 100) ** 2), 1);
    }
}
