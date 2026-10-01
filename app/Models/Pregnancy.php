<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Pregnancy extends Model
{
    use Auditable;

    protected $fillable = ['lmp', 'edd', 'edd_by_scan', 'gravida', 'parity', 'abortions', 'living_children', 'risk_factors', 'notes'];

    protected function casts(): array
    {
        return [
            'lmp' => 'date',
            'edd' => 'date',
            'edd_by_scan' => 'boolean',
            'risk_factors' => 'array',
            'labour_started_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ancVisits(): HasMany
    {
        return $this->hasMany(AncVisit::class)->orderByDesc('visit_date')->orderByDesc('id');
    }

    public function partograph(): HasMany
    {
        return $this->hasMany(PartographEntry::class)->orderBy('recorded_at');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function postnatalVisits(): HasMany
    {
        return $this->hasMany(PostnatalVisit::class)->orderByDesc('visit_date');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Naegele's rule: EDD = LMP + 280 days.
     */
    public static function eddFromLmp(Carbon $lmp): Carbon
    {
        return $lmp->copy()->addDays(280);
    }

    /**
     * Gestational age in days on a date, derived from the EDD
     * (so a dating-scan EDD is respected).
     */
    public function gestationDays(?Carbon $on = null): int
    {
        $on ??= today();

        return 280 - (int) $on->copy()->startOfDay()->diffInDays($this->edd->copy()->startOfDay(), false);
    }

    /**
     * e.g. "32w 4d"
     */
    public function gestationLabel(?Carbon $on = null): string
    {
        $days = max(0, $this->gestationDays($on));

        return intdiv($days, 7).'w '.($days % 7).'d';
    }

    public function trimester(): int
    {
        $weeks = intdiv(max(0, $this->gestationDays()), 7);

        return $weeks < 14 ? 1 : ($weeks < 28 ? 2 : 3);
    }

    /**
     * Booked risk factors plus any danger signs seen at ANC visits.
     */
    public function isHighRisk(): bool
    {
        return ! empty($this->risk_factors)
            || $this->ancVisits->contains(fn (AncVisit $v) => $v->alerts() !== []);
    }

    /**
     * G2P1+0 notation.
     */
    public function obstetricFormula(): string
    {
        return "G{$this->gravida}P{$this->parity}+{$this->abortions}";
    }

    public function riskLabels(): array
    {
        $all = config('emr.maternity.risk_factors');

        return array_values(array_intersect_key($all, array_flip($this->risk_factors ?? [])));
    }
}
