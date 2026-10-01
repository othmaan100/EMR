<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\InsuranceProvider>
 */
class InsuranceProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Health',
            'code' => Str::upper(Str::random(5)),
            'type' => 'insurance',
            'is_active' => true,
        ];
    }
}
