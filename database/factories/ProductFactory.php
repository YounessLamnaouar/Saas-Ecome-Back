<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    private array $nouns = [
        'Dress', 'Shirt', 'Jeans', 'Jacket', 'Sneakers', 'Hoodie', 'Skirt', 'Blazer',
        'Sweater', 'Shorts', 'Boots', 'Sandals', 'Backpack', 'Handbag', 'Belt', 'Scarf',
    ];

    private array $adjectives = [
        'Classic', 'Slim-Fit', 'Oversized', 'Vintage', 'Minimalist', 'Bold', 'Everyday', 'Premium',
    ];

    public function definition(): array
    {
        $title = $this->faker->randomElement($this->adjectives) . ' ' . $this->faker->randomElement($this->nouns);
        $price = $this->faker->randomFloat(2, 15, 250);
        $hasDiscount = $this->faker->boolean(35);

        return [
            'category_id' => Category::inRandomOrder()->first()?->id ?? Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'description' => $this->faker->paragraph(3),
            'price' => $price,
            'discount_percentage' => $hasDiscount ? $this->faker->numberBetween(5, 50) : 0,
            'brand' => $this->faker->randomElement(['Nova', 'Atlas', 'Linen&Co', 'Urbane', 'Drift', 'Marlo']),
            'sku' => strtoupper(Str::random(3)) . '-' . $this->faker->unique()->numberBetween(1000, 99999),
            'stock' => $this->faker->numberBetween(0, 120),
            'rating' => $this->faker->randomFloat(1, 2.5, 5),
            'thumbnail' => "https://picsum.photos/seed/prod-{$this->faker->unique()->numberBetween(1, 100000)}/600/600",
        ];
    }
}
