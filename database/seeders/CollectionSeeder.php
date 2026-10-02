<?php

namespace Database\Seeders;

use App\Models\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'New Arrivals' => true,
            'Summer Sale' => true,
            'Best Sellers' => true,
            'Winter Edit' => false,
            'Staff Picks' => false,
        ];

        foreach ($definitions as $name => $featured) {
            Collection::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => fake()->sentence(10),
                    'image' => "https://picsum.photos/seed/col-{$name}/600/400",
                    'is_featured' => $featured,
                ]
            );
        }
    }
}
