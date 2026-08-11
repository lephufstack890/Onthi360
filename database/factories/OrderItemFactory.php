<?php

namespace Database\Factories;

use App\Enums\AccessScope;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'scope' => AccessScope::PersonalLearning,
            'quantity' => 1,
            'unit_price' => 99000,
        ];
    }
}
