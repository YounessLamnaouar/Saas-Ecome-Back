<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Women', 'Men', 'Kids', 'Shoes', 'Accessories', 'Bags', 'Activewear', 'Outerwear',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(12),
            'image' => "https://picsum.photos/seed/cat-{$name}/600/400",
        ];
    }
}
