<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use Auditable, HasPrices;

    /** Charged when a patient is registered. */
    public const REGISTRATION = 'REG';

    /** Consultation fee used when a clinic has no fee of its own. */
    public const CONSULTATION = 'CONSULT';

    public const CATEGORIES = ['Registration', 'Consultation', 'Procedure', 'Nursing', 'Accommodation', 'Maternity', 'Dental', 'Eye', 'Physiotherapy', 'Other'];

    public const EYE_REFRACTION = 'EYE-REFRACT';

    public const PHYSIO_ASSESSMENT = 'PHYSIO-ASSESS';

    public const PHYSIO_SESSION = 'PHYSIO-SESSION';

    /** Maternity charges looked up by code (unpriced = not charged). */
    public const ANC_BOOKING = 'ANC-BOOK';

    public const DELIVERY_VAGINAL = 'DEL-SVD';

    public const DELIVERY_CAESAREAN = 'DEL-CS';

    /** Theatre use fee added to every completed operation (if priced). */
    public const THEATRE_FEE = 'THEATRE';

    protected $fillable = ['code', 'name', 'category', 'clinic_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
