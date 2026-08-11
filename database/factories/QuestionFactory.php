<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\QuestionType;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bank_id' => QuestionBank::factory(),
            'code' => fake()->unique()->bothify('Q-####'),
            'type' => QuestionType::Mcq,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'points' => 10,
            'grading_config' => ['correct_options' => [1], 'options' => ['A', 'B', 'C', 'D']],
            'status' => ContentStatus::Draft,
        ];
    }

    public function coding(): static
    {
        return $this->state([
            'type' => QuestionType::Coding,
            'grading_config' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(['status' => ContentStatus::Published]);
    }
}
