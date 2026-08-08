<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'target' => fake()->randomElement(['web', 'both']),
            'description' => fake()->optional()->sentence(),
            'seed' => null,
        ];
    }
}
