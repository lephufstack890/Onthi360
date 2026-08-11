<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_no' => fake()->unique()->bothify('ORD-########'),
            'buyer_id' => User::factory(),
            'status' => OrderStatus::Created,
            'payment_method' => PaymentMethod::Offline,
            'total_amount' => 0,
        ];
    }
}
