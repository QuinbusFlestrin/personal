<?php

namespace Database\Factories;

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedFilter>
 */
class SavedFilterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'filter_params' => ['category_ids' => [], 'canton_ids' => []],
            'frequency' => fake()->randomElement([SavedFilter::FREQUENCY_DAILY, SavedFilter::FREQUENCY_WEEKLY]),
            'is_active' => true,
        ];
    }
}
