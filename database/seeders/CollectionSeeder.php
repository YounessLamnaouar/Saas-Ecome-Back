<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['everyday-style', 'Everyday Style', 'Easy-to-wear shirts, tops and dresses for your everyday wardrobe.', ['mens-shirts', 'tops', 'womens-dresses'], true],
            ['shoes-and-bags', 'Shoes & Bags', 'Find your next favourite pair and the bag to go with it.', ['mens-shoes', 'womens-shoes', 'womens-bags'], true],
            ['finishing-touches', 'Finishing Touches', 'Watches, jewellery and sunglasses that complete the look.', ['mens-watches', 'womens-watches', 'womens-jewellery', 'sunglasses'], true],
            ['beauty-and-fragrance', 'Beauty & Fragrance', 'Makeup, skincare and signature scents for your daily routine.', ['beauty', 'skin-care', 'fragrances'], true],
            ['home-and-living', 'Home & Living', 'Thoughtful furniture, decorative accents and kitchen essentials.', ['furniture', 'home-decoration', 'kitchen-accessories'], true],
            ['tech-essentials', 'Tech Essentials', 'Discover phones, laptops, tablets and useful everyday accessories.', ['smartphones', 'laptops', 'tablets', 'mobile-accessories'], true],
            ['active-days', 'Active Days', 'Equipment and accessories for training, play and time outdoors.', ['sports-accessories'], false],
            ['pantry-essentials', 'Pantry Essentials', 'Fresh ingredients and everyday groceries for a well-stocked kitchen.', ['groceries'], false],
        ];

        foreach ($definitions as [$slug, $name, $description, $categories, $featured]) {
            $products = Product::where('sku', 'like', 'DUMMYJSON-%')
                ->whereHas('category', fn ($query) => $query->whereIn('slug', $categories))
                ->whereNotNull('thumbnail')->where('thumbnail', '!=', '')
                ->orderByDesc('rating')->orderBy('id')->get();
            if ($products->isEmpty()) {
                continue;
            }
            $collection = Collection::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description,
                'image' => $products->first()->thumbnail, 'is_featured' => $featured,
            ]);
            $collection->products()->sync($products->modelKeys());
        }
    }
}
