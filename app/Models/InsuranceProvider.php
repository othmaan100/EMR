<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceProvider extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'code', 'type', 'coverage_percent', 'requires_authorization', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'requires_authorization' => 'boolean', 'coverage_percent' => 'integer'];
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
