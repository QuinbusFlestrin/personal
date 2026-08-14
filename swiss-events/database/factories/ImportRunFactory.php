<?php

namespace Database\Factories;

use App\Models\ImportRun;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'started_at' => now(),
            'finished_at' => now(),
            'status' => ImportRun::STATUS_SUCCESS,
        ];
    }
}
