<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

class VersionFactory extends Factory
{
    protected $model = Version::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ];
    }
}
