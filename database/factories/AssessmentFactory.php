<?php

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'type' => AssessmentType::Practice,
            'total_points' => 10,
            'status' => ContentStatus::Published,
        ];
    }
}
