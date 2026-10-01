<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AncVisit extends Model
{
    use Auditable;

    protected $fillable = ['visit_date', 'weight', 'systolic', 'diastolic', 'fundal_height', 'presentation', 'fetal_heart_rate',
        'fetal_movement', 'urine_protein', 'oedema', 'haemoglobin', 'interventions', 'complaints', 'notes', 'next_visit'];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_visit' => 'date',
            'interventions' => 'array',
            'fetal_movement' => 'boolean',
            'weight' => 'float',
            'haemoglobin' => 'float',
        ];
    }

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Danger signs on this visit. Decision aid only.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];
        $gaWeeks = $this->pregnancy ? intdiv($this->pregnancy->gestationDays($this->visit_date), 7) : null;
        $proteinuria = in_array($this->urine_protein, ['+', '++', '+++'], true);

        if ($this->systolic >= 160 || $this->diastolic >= 110) {
            $alerts[] = 'Severe hypertension (BP '.$this->systolic.'/'.$this->diastolic.')';
        } elseif ($this->systolic >= 140 || $this->diastolic >= 90) {
            $alerts[] = 'Raised BP'.($proteinuria ? ' with proteinuria — suspect pre-eclampsia' : '');
        }
        if ($this->fetal_heart_rate && ($this->fetal_heart_rate < 110 || $this->fetal_heart_rate > 160)) {
            $alerts[] = "Abnormal fetal heart rate ({$this->fetal_heart_rate}/min)";
        }
        if ($this->fetal_movement === false && $gaWeeks >= 24) {
            $alerts[] = 'Reduced / absent fetal movements';
        }
        // After 24 weeks fundal height (cm) ≈ gestation (weeks) ± 3.
        if ($this->fundal_height && $gaWeeks >= 24 && abs($this->fundal_height - $gaWeeks) > 3) {
            $alerts[] = "Fundal height {$this->fundal_height} cm does not match {$gaWeeks} weeks";
        }
        if ($this->haemoglobin !== null && $this->haemoglobin < 7) {
            $alerts[] = "Severe anaemia (Hb {$this->haemoglobin} g/dL)";
        } elseif ($this->haemoglobin !== null && $this->haemoglobin < 11) {
            $alerts[] = "Anaemia (Hb {$this->haemoglobin} g/dL)";
        }

        return $alerts;
    }
}
