<?php

namespace App\Models\Concerns;

use App\Models\Price;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anything that can appear on a bill: Service, LabTest, ImagingTest, Drug.
 */
trait HasPrices
{
    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'billable');
    }

    /**
     * Default (self-pay) price, or the provider-specific one when asked.
     */
    public function priceFor(?int $insuranceProviderId = null): ?float
    {
        $prices = $this->relationLoaded('prices') ? $this->prices : $this->prices()->get();

        $price = $insuranceProviderId
            ? ($prices->firstWhere('insurance_provider_id', $insuranceProviderId) ?? $prices->firstWhere('insurance_provider_id', null))
            : $prices->firstWhere('insurance_provider_id', null);

        return $price ? (float) $price->amount : null;
    }

    /**
     * Name shown on bills.
     */
    public function billingLabel(): string
    {
        return $this->label ?? $this->name;
    }
}
