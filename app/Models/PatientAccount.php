<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A patient's portal login (guard "patient"). Kept apart from staff users.
 */
class PatientAccount extends Authenticatable
{
    protected $fillable = [];

    protected $hidden = ['password', 'activation_code', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'activation_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Children registered at birth under this patient (mother_id).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Patient::class, 'mother_id', 'patient_id');
    }

    public function isActivated(): bool
    {
        return $this->password !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Patients this login may view: themselves and their children.
     *
     * @return list<int>
     */
    public function patientIds(): array
    {
        return [$this->patient_id, ...$this->children()->pluck('id')->all()];
    }
}
