<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pharmacy_id' => Pharmacy::factory(),
            'product_id'  => Product::factory(),
            'quantity'    => $this->faker->numberBetween(5, 100),
            'price'       => $this->faker->randomFloat(2, 500, 15000),
        ];
    }
}
