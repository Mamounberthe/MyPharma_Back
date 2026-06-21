<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'pharmacy_id'      => Pharmacy::factory(),
            'total_price'      => $this->faker->randomFloat(2, 1000, 50000),
            'status'           => 'pending',
            'delivery_address' => $this->faker->streetAddress() . ', Dakar',
        ];
    }
}
