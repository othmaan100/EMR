<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SurgicalProcedure extends Model
{
    use Auditable, HasPrices;

    public const CAESAREAN = 'CS';

    protected $fillable = ['code', 'name', 'specialty', 'typical_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function typicalMinutes(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v === '' ? null : $v);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
