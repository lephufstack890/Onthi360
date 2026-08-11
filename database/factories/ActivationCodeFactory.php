<?php

namespace Database\Factories;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivationCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('????-????-????'),
            'product_id' => Product::factory(),
            'scope' => AccessScope::PersonalLearning,
            'status' => ActivationCodeStatus::Unused,
            'validity_months' => 12,
        ];
    }
}
