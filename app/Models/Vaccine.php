<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Vaccine extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'dose_label', 'age_days', 'route', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function sortOrder(): Attribute
    {
        return Attribute::make(get: fn ($v) => (int) $v, set: fn ($v) => (int) ($v ?? 0));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeScheduled(Builder $query): void
    {
        $query->orderBy('age_days')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * e.g. "Pentavalent — Dose 1"
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => trim($this->name.($this->dose_label ? ' — '.$this->dose_label : '')));
    }

    /**
     * "At birth", "6 weeks", "9 months" …
     */
    public function ageLabel(): string
    {
        $d = $this->age_days;

        return match (true) {
            $d === 0 => 'At birth',
            $d < 98 && $d % 7 === 0 => ($d / 7).' weeks',
            $d < 730 => round($d / 30.4).' months',
            default => round($d / 365.25, 1).' years',
        };
    }
}
