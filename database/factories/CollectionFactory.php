<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CollectionFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'New Arrivals', 'Summer Sale', 'Best Sellers', 'Winter Edit', 'Staff Picks', 'Weekend Drop',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(10),
            'image' => "https://picsum.photos/seed/col-{$name}/600/400",
            'is_featured' => $this->faker->boolean(40),
        ];
    }
}
