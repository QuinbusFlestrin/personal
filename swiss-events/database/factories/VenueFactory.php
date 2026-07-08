<?php

namespace Database\Factories;

use App\Models\Canton;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'venue_type' => fake()->randomElement([
                Venue::TYPE_MUSEUM, Venue::TYPE_PARK, Venue::TYPE_HISTORICAL_BUILDING,
                Venue::TYPE_THEATRE, Venue::TYPE_GENERIC,
            ]),
            'canton_id' => Canton::factory(),
            'status' => 'published',
        ];
    }
}
