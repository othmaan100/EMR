<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const PAYMENT_TYPES = [
        'self_pay' => 'Self-pay (Cash)',
        'insurance' => 'Health Insurance / HMO',
        'corporate' => 'Corporate / Company',
        'staff' => 'Staff',
        'free' => 'Free / Waiver',
    ];

    protected $guarded = ['id', 'hospital_number', 'registered_by', 'photo', 'nin_verified_at', 'nin_verification_ref', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_death' => 'date',
            'insurance_expiry' => 'date',
            'dob_estimated' => 'boolean',
            'is_deceased' => 'boolean',
            'nin_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            $patient->hospital_number ??= Sequence::patientNumber();
        });
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function imagingOrders(): HasMany
    {
        return $this->hasMany(ImagingOrder::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function pregnancies(): HasMany
    {
        return $this->hasMany(Pregnancy::class)->latest('id');
    }

    public function immunizations(): HasMany
    {
        return $this->hasMany(Immunization::class);
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'mother_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Patient::class, 'mother_id');
    }

    public function ageInYears(): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(now()) : null;
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Total the patient still owes across all bills.
     */
    public function outstandingBalance(): float
    {
        return round((float) BillItem::unpaid()->whereIn('bill_id', $this->bills()->select('id'))
            ->sum(\Illuminate\Support\Facades\DB::raw('patient_amount - discount_amount - paid_amount')), 2);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->title, $this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }

    /**
     * "Surname, Other names" — the usual clinical listing format.
     */
    protected function listName(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper($this->last_name).', '.collect([$this->first_name, $this->middle_name])->filter()->implode(' '));
    }

    protected function age(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->date_of_birth) {
                return null;
            }

            $end = $this->date_of_death ?? now();
            $diff = $this->date_of_birth->diff($end);

            return match (true) {
                $diff->y >= 2 => $diff->y.' yrs',
                $diff->y === 1 || $diff->m >= 1 => ($diff->y * 12 + $diff->m).' mths',
                default => $diff->days.' days',
            };
        });
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    public function paymentLabel(): string
    {
        $label = self::PAYMENT_TYPES[$this->payment_type] ?? $this->payment_type;

        return $this->insuranceProvider ? $label.' — '.$this->insuranceProvider->name : $label;
    }

    /**
     * Search by hospital/legacy number, name(s), phone, national ID or insurance number.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $like = "%$term%";
            $q->where('hospital_number', 'like', $like)
                ->orWhere('legacy_number', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('national_id', $term)
                ->orWhere('insurance_number', $term);

            // Every word must match some name part: "john doe" finds DOE, John.
            $q->orWhere(function (Builder $names) use ($term) {
                foreach (preg_split('/\s+/', $term) as $word) {
                    $names->where(fn (Builder $n) => $n
                        ->where('first_name', 'like', "%$word%")
                        ->orWhere('middle_name', 'like', "%$word%")
                        ->orWhere('last_name', 'like', "%$word%"));
                }
            });
        });
    }
}
