<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'idea' => fake()->paragraph(),
            'target' => fake()->randomElement(['web', 'mobile', 'both']),
            'stack' => fake()->optional()->word(),
        ];
    }
}