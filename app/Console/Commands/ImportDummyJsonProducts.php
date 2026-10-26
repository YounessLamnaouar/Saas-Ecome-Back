<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportDummyJsonProducts extends Command
{
    protected $signature = 'catalog:import-dummyjson';

    protected $description = 'Import DummyJSON products and images without overwriting existing products or stock';

    public function handle(): int
    {
        $response = Http::acceptJson()->timeout(30)->get('https://dummyjson.com/products', ['limit' => 0])->throw();
        $products = $response->json('products');
        if (! is_array($products) || ! $products) {
            $this->error('DummyJSON returned no products.');

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($products) {
            $count = 0;
            foreach ($products as $row) {
                $sku = 'DUMMYJSON-'.$row['id'];
                if (Product::where('sku', $sku)->exists()) {
                    continue;
                }
                $category = Category::firstOrCreate(['slug' => $row['category']], ['name' => Str::headline($row['category']), 'image' => $row['thumbnail']]);
                $product = Product::create([
                    'category_id' => $category->id, 'title' => $row['title'],
                    'slug' => 'dummyjson-'.$row['id'].'-'.Str::slug($row['title']),
                    'description' => $row['description'], 'price' => $row['price'],
                    'discount_percentage' => (int) round($row['discountPercentage'] ?? 0),
                    'stock' => $row['stock'] ?? 0, 'rating' => $row['rating'] ?? 0,
                    'brand' => $row['brand'] ?? null, 'sku' => $sku, 'thumbnail' => $row['thumbnail'],
                ]);
                foreach ($row['images'] ?? [] as $position => $url) {
                    $product->images()->create(['url' => $url, 'position' => $position]);
                }
                $count++;
            }

            return $count;
        });
        $this->info("Imported {$created} products; existing products preserved.");

        return self::SUCCESS;
    }
}
