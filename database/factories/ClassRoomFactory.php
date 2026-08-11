<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'code' => fake()->unique()->bothify('??-####'),
            'name' => fake()->words(3, true),
            'status' => 'active',
        ];
    }
}
