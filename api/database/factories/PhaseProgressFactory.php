<?php

namespace Database\Factories;

use App\Models\PhaseProgress;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhaseProgressFactory extends Factory
{
    protected $model = PhaseProgress::class;

    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'phase_key' => fake()->word(),
            'done' => fake()->boolean(),
        ];
    }
}
