<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurateDemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_preserves_imported_and_custom_products_and_builds_repeatable_collections(): void
    {
        $oldCategory = Category::factory()->create(['slug' => 'women', 'image' => 'https://picsum.photos/seed/cat-Women/600/400']);
        $shoes = Category::factory()->create(['slug' => 'mens-shoes']);
        $legacy = Product::factory()->create(['category_id' => $oldCategory->id]);
        $imported = Product::factory()->create(['category_id' => $shoes->id, 'sku' => 'DUMMYJSON-1', 'stock' => 13, 'thumbnail' => 'https://cdn.dummyjson.com/shoes.webp']);
        $custom = Product::factory()->create(['category_id' => $oldCategory->id, 'sku' => 'PZLDMFTR', 'thumbnail' => 'https://store.example/shoes.jpg']);
        $oldCollection = Collection::create(['name' => 'Old Demo', 'slug' => 'old-demo', 'image' => 'https://picsum.photos/seed/col-Old/600/400']);
        $oldCollection->products()->attach($legacy);
        $customCollection = Collection::create(['name' => 'Football', 'slug' => 'football']);
        $customCollection->products()->attach($custom);

        $this->artisan('catalog:curate-demo')->assertSuccessful();
        $this->assertDatabaseMissing('products', ['id' => $legacy->id]);
        $this->assertDatabaseMissing('categories', ['id' => $oldCategory->id]);
        $this->assertDatabaseMissing('collections', ['id' => $oldCollection->id]);
        $this->assertSame(13, (int) $imported->fresh()->stock);
        $this->assertEquals($shoes->id, $custom->fresh()->category_id);
        $this->assertTrue($customCollection->products()->whereKey($custom->id)->exists());
        $curated = Collection::where('slug', 'shoes-and-bags')->firstOrFail();
        $this->assertSame($imported->thumbnail, $curated->image);
        $this->assertEquals([$imported->id], $curated->products()->pluck('products.id')->all());
        $this->artisan('catalog:curate-demo')->assertSuccessful();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('collections', 2);
        $this->assertDatabaseCount('collection_product', 2);
    }

    public function test_cleanup_refuses_to_remove_legacy_catalog_without_imported_products(): void
    {
        $product = Product::factory()->create();
        $this->artisan('catalog:curate-demo')->assertFailed();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
