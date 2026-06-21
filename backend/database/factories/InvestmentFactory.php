<?php

namespace Database\Factories;

use App\Models\AssetType;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    public function definition(): array
    {
        $assetType = AssetType::inRandomOrder()->first() ?? AssetType::first();
        $status = $this->faker->randomElement(['active', 'active', 'active', 'sold', 'archived']);

        return [
            'user_id' => User::factory(),
            'asset_type_id' => $assetType?->id ?? 1,
            'name' => $this->faker->words(2, true),
            'isin' => $this->faker->optional(0.4)->regexify('[A-Z]{2}[A-Z0-9]{10}'),
            'symbol' => $this->faker->optional(0.7)->bothify('???'),
            'quantity' => $this->faker->randomFloat(6, 0.1, 1000),
            'unit' => $this->faker->randomElement(['unit', 'part', 'action', 'gramme', 'euros', 'm²']),
            'purchase_price' => $this->faker->optional(0.7)->randomFloat(2, 1, 5000),
            'purchase_currency' => 'EUR',
            'purchase_date' => $this->faker->optional(0.7)->dateTimeBetween('-5 years', '-1 month')?->format('Y-m-d'),
            'manual_value' => null,
            'manual_value_updated_at' => null,
            'currency' => 'EUR',
            'provider_id' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'status' => $status,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'manual_value' => $this->faker->randomFloat(2, 1000, 500000),
            'manual_value_updated_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }
}
