<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ProductType::Book,
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'price' => fake()->numberBetween(50000, 300000),
            'status' => ContentStatus::Published,
            'visibility' => Visibility::Private,
            'duration_months' => 12,
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => Visibility::Public]);
    }
}
