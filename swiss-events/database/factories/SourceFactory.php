<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Events',
            'type' => Source::TYPE_JSON_API,
            'config' => ['url' => fake()->url()],
            'trust_level' => Source::TRUST_UNTRUSTED,
            'is_active' => true,
        ];
    }

    public function trusted(): static
    {
        return $this->state(['trust_level' => Source::TRUST_TRUSTED]);
    }
}
