<?php

namespace Database\Factories;

use App\Models\Canton;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Canton>
 */
class CantonFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
