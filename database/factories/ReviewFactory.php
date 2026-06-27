<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'rating'      => $this->faker->numberBetween(1, 5),
            'comment'     => $this->faker->sentence(),
        ];
    }
}
