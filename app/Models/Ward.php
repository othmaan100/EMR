<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    use Auditable;

    public const TYPES = ['General', 'Male Medical', 'Female Medical', 'Surgical', 'Paediatric', 'Maternity', 'Labour', 'Neonatal', 'ICU', 'HDU', 'Private', 'Isolation'];

    protected $fillable = ['name', 'code', 'type', 'gender', 'department_id', 'service_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class)->orderByRaw('LENGTH(label)')->orderBy('label');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Service whose price is the daily bed charge. */
    public function bedCharge(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function accepts(Patient $patient): bool
    {
        return $this->gender === 'any' || $this->gender === $patient->gender;
    }
}
