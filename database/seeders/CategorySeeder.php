<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        collect(['Women', 'Men', 'Kids', 'Shoes', 'Accessories', 'Bags', 'Activewear', 'Outerwear'])
            ->each(fn ($name) => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => fake()->sentence(12),
                    'image' => "https://picsum.photos/seed/cat-{$name}/600/400",
                ]
            ));
    }
}
