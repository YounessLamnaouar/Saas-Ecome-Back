<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Database\Seeders\CollectionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CurateDemoCatalog extends Command
{
    protected $signature = 'catalog:curate-demo';
    protected $description = 'Back up and remove legacy placeholder catalog data, then curate DummyJSON collections';

    public function handle(): int
    {
        if (! Product::where('sku', 'like', 'DUMMYJSON-%')->exists()) {
            $this->error('Import the DummyJSON catalog before removing legacy demo data.');
            return self::FAILURE;
        }

        $legacy = Product::where('thumbnail', 'like', 'https://picsum.photos/seed/prod-%')->where('sku', 'not like', 'DUMMYJSON-%')->get();
        $categories = Category::where('image', 'like', 'https://picsum.photos/seed/cat-%')->get();
        $collections = Collection::where('image', 'like', 'https://picsum.photos/seed/col-%')->get();
        $ids = $legacy->modelKeys();
        $backup = [
            'products' => $legacy->toArray(), 'categories' => $categories->toArray(), 'collections' => $collections->toArray(),
            'product_images' => DB::table('product_images')->whereIn('product_id', $ids)->get(),
            'product_tag' => DB::table('product_tag')->whereIn('product_id', $ids)->get(),
            'collection_product' => DB::table('collection_product')->whereIn('product_id', $ids)->orWhereIn('collection_id', $collections->modelKeys())->get(),
            'order_item_links' => DB::table('order_items')->whereIn('product_id', $ids)->get(['id', 'product_id']),
            'custom_product_categories' => Product::whereIn('category_id', $categories->modelKeys())->whereNotIn('id', $ids)->get(['id', 'category_id']),
        ];
        $directory = storage_path('app/private/catalog-backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/legacy-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
        File::put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($ids, $categories, $collections) {
            // Retain historical order names, prices and quantities when their demo product is removed.
            DB::table('order_items')->whereIn('product_id', $ids)->update(['product_id' => null]);
            Product::whereIn('id', $ids)->delete();
            Collection::whereIn('id', $collections->modelKeys())->delete();
            // The manually added football shoes remain a real store product.
            $shoes = Category::where('slug', 'mens-shoes')->first();
            if ($shoes) {
                Product::where('sku', 'PZLDMFTR')->whereIn('category_id', $categories->modelKeys())->update(['category_id' => $shoes->id]);
            }
            Category::whereIn('id', $categories->modelKeys())->whereDoesntHave('products')->delete();
            (new CollectionSeeder)->run();
        });

        $this->info('Removed '.$legacy->count().' placeholder products and '.$collections->count().' placeholder collections.');
        $this->info('Catalog now has '.Product::count().' products, '.Category::count().' categories and '.Collection::count().' collections.');
        $this->info('Backup: '.$path);
        return self::SUCCESS;
    }
}
