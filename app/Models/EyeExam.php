<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EyeExam extends Model
{
    public const IOP_HIGH = 21; // mmHg; above this suggests glaucoma risk

    protected $fillable = [
        'va_right', 'va_left', 'va_right_corrected', 'va_left_corrected', 'iop_right', 'iop_left',
        'sph_right', 'cyl_right', 'axis_right', 'add_right', 'anterior_right', 'fundus_right',
        'sph_left', 'cyl_left', 'axis_left', 'add_left', 'anterior_left', 'fundus_left',
        'pd', 'diagnosis', 'plan', 'spectacles_prescribed', 'lens_notes',
    ];

    protected function casts(): array
    {
        return ['spectacles_prescribed' => 'boolean', 'iop_right' => 'float', 'iop_left' => 'float',
            'sph_right' => 'float', 'cyl_right' => 'float', 'add_right' => 'float', 'sph_left' => 'float', 'cyl_left' => 'float', 'add_left' => 'float'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examined_by');
    }

    /**
     * Warnings for the chart: raised pressure, poor vision.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];
        foreach (['right' => 'Right', 'left' => 'Left'] as $eye => $label) {
            if ($this->{"iop_{$eye}"} !== null && $this->{"iop_{$eye}"} > self::IOP_HIGH) {
                $alerts[] = "{$label} eye pressure {$this->{"iop_{$eye}"}} mmHg (above ".self::IOP_HIGH.')';
            }
            $best = $this->{"va_{$eye}_corrected"} ?: $this->{"va_{$eye}"};
            if ($best && in_array($best, ['6/60', '3/60', '1/60', 'CF', 'HM', 'PL', 'NPL'], true)) {
                $alerts[] = "{$label} eye best vision {$best} (severe visual impairment or worse)";
            }
        }

        return $alerts;
    }

    /**
     * "−1.25 / −0.50 × 180" style prescription for one eye.
     */
    public function rx(string $eye): string
    {
        $sph = $this->{"sph_{$eye}"};
        if ($sph === null && $this->{"cyl_{$eye}"} === null) {
            return '—';
        }
        $fmt = fn (?float $v) => $v === null ? 'plano' : ($v == 0 ? 'plano' : sprintf('%+.2f', $v));
        $out = $fmt($sph).' DS';
        if ($this->{"cyl_{$eye}"}) {
            $out .= ' / '.sprintf('%+.2f', $this->{"cyl_{$eye}"}).' DC × '.($this->{"axis_{$eye}"} ?? '?').'°';
        }
        if ($this->{"add_{$eye}"}) {
            $out .= ' · Add '.sprintf('%+.2f', $this->{"add_{$eye}"});
        }

        return $out;
    }
}
