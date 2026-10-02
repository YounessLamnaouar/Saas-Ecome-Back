<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $tags = Tag::all();
        $collections = Collection::all();

        Product::factory()
            ->count(150)
            ->create()
            ->each(function (Product $product) use ($tags, $collections) {
                // 1-3 random tags per product
                $product->tags()->sync($tags->random(random_int(1, 3))->pluck('id'));

                // up to 4 gallery images in addition to the thumbnail
                collect(range(1, random_int(1, 4)))->each(
                    fn ($i) => $product->images()->create([
                        'url' => "https://picsum.photos/seed/{$product->slug}-{$i}/800/800",
                        'position' => $i,
                    ])
                );

                // ~30% chance of landing in 1-2 collections
                if (random_int(1, 100) <= 30) {
                    $product->collections()->sync($collections->random(random_int(1, 2))->pluck('id'));
                }
            });
    }
}
