<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PharmacyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => $this->faker->company() . ' Pharmacie',
            'address'            => $this->faker->streetAddress(),
            'phone'              => $this->faker->phoneNumber(),
            'latitude'           => $this->faker->latitude(5, 15),
            'longitude'          => $this->faker->longitude(-17, 5),
            'rating'             => $this->faker->randomFloat(2, 3.0, 5.0),
            'delivery_available' => true,
            'is_on_call'         => false,
            'is_partner'         => true,
            'status'             => 'active',
        ];
    }
}
