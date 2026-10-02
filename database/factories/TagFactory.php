<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'featured', 'sale', 'trending', 'new', 'premium', 'limited', 'bestseller', 'eco',
            ]),
        ];
    }
}
