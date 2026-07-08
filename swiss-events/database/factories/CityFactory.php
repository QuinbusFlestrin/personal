<?php

namespace Database\Factories;

use App\Models\Canton;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'canton_id' => Canton::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
