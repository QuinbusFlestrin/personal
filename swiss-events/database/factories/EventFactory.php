<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $startsAt = fake()->dateTimeBetween('now', '+3 months');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->paragraph(),
            'venue_id' => Venue::factory(),
            'category_id' => Category::factory(),
            'starts_at' => $startsAt,
            'status' => Event::STATUS_PUBLISHED,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(['status' => Event::STATUS_PENDING_REVIEW]);
    }

    public function published(): static
    {
        return $this->state(['status' => Event::STATUS_PUBLISHED]);
    }
}
