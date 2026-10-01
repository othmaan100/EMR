<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\VitalAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    use Auditable, HasFactory;

    /**
     * field => [label, unit, short label]
     */
    public const FIELDS = [
        'temperature' => ['Temperature', '°C', 'Temp'],
        'systolic' => ['Systolic BP', 'mmHg', 'SBP'],
        'diastolic' => ['Diastolic BP', 'mmHg', 'DBP'],
        'pulse' => ['Pulse', '/min', 'HR'],
        'respiratory_rate' => ['Respiratory rate', '/min', 'RR'],
        'spo2' => ['SpO₂', '%', 'SpO₂'],
        'weight' => ['Weight', 'kg', 'Wt'],
        'height' => ['Height', 'cm', 'Ht'],
        'bmi' => ['BMI', 'kg/m²', 'BMI'],
        'pain_score' => ['Pain score', '/10', 'Pain'],
        'blood_glucose' => ['Blood glucose', 'mmol/L', 'RBG'],
    ];

    public const CONSCIOUSNESS = [
        'A' => 'Alert',
        'C' => 'New confusion',
        'V' => 'Responds to voice',
        'P' => 'Responds to pain',
        'U' => 'Unresponsive',
    ];

    protected $fillable = [
        'temperature', 'systolic', 'diastolic', 'pulse', 'respiratory_rate', 'spo2', 'on_oxygen',
        'consciousness', 'weight', 'height', 'pain_score', 'blood_glucose', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
            'on_oxygen' => 'boolean',
            'temperature' => 'float',
            'weight' => 'float',
            'height' => 'float',
            'bmi' => 'float',
            'blood_glucose' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Derived values are always recalculated from the raw readings.
        static::saving(function (VitalSign $v) {
            $v->bmi = VitalAssessment::bmi($v->weight, $v->height);
            $v->news2_score = VitalAssessment::news2($v, $v->patientAgeYears());
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function patientAgeYears(): ?int
    {
        $dob = $this->patient?->date_of_birth;

        return $dob ? (int) $dob->diffInYears($this->recorded_at ?? now()) : null;
    }

    /**
     * @return array<string, string>
     */
    public function flags(): array
    {
        return VitalAssessment::flags($this, $this->patientAgeYears());
    }

    public function news2Risk(): ?array
    {
        return VitalAssessment::news2Risk($this, $this->patientAgeYears());
    }

    public function bloodPressure(): ?string
    {
        return $this->systolic && $this->diastolic ? "{$this->systolic}/{$this->diastolic}" : null;
    }

    /**
     * Human-readable value with unit, e.g. "37.2 °C".
     */
    public function display(string $field): ?string
    {
        $value = $this->{$field};
        if ($value === null) {
            return null;
        }

        $formatted = is_float($value) ? rtrim(rtrim(number_format($value, 2), '0'), '.') : $value;

        return $formatted.' '.self::FIELDS[$field][1];
    }
}
