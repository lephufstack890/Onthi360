<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => 'chapter',
            'title' => fake()->sentence(2),
            'order' => 1,
            'status' => ContentStatus::Published,
        ];
    }
}
