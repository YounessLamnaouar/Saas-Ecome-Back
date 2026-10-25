<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Collection $col1;
    protected Collection $col2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Dresses',
            'slug' => 'dresses',
        ]);

        $this->col1 = Collection::create([
            'name' => 'Summer Sale',
            'slug' => 'summer-sale',
            'description' => 'Hot summer discounts',
        ]);

        $this->col2 = Collection::create([
            'name' => 'New Arrivals',
            'slug' => 'new-arrivals',
            'description' => 'Fresh styles',
        ]);
    }

    public function test_can_attach_collections_on_product_create(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'title' => 'Silk Summer Gown',
            'description' => 'Light and breezy',
            'price' => 129.99,
            'category_slug' => $this->category->slug,
            'collection_ids' => [$this->col1->id, $this->col2->id],
            'tags' => ['silk', 'elegant'],
        ];

        $response = $this->postJson('/api/v1/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Silk Summer Gown')
            ->assertJsonCount(2, 'data.collections');

        $productId = $response->json('data.id');
        $product = Product::with('collections')->find($productId);

        $this->assertCount(2, $product->collections);
        $this->assertTrue($product->collections->contains($this->col1));
        $this->assertTrue($product->collections->contains($this->col2));
    }

    public function test_can_update_product_collections(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);
        $product->collections()->sync([$this->col1->id]);

        $this->assertCount(1, $product->fresh()->collections);

        // Update to only col2
        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'collection_ids' => [$this->col2->id],
        ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertCount(1, $product->collections);
        $this->assertTrue($product->collections->contains($this->col2));
        $this->assertFalse($product->collections->contains($this->col1));
    }

    public function test_partial_update_does_not_detach_collections_when_collection_ids_is_omitted(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
        ]);
        $product->collections()->sync([$this->col1->id, $this->col2->id]);

        // Partial update without collection_ids
        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'price' => 75.00,
        ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals(75.00, (float) $product->price);
        $this->assertCount(2, $product->collections);
    }

    public function test_can_filter_products_by_collection_slug(): void
    {
        $prodInCol = Product::factory()->create(['category_id' => $this->category->id]);
        $prodInCol->collections()->attach($this->col1->id);

        $prodNotInCol = Product::factory()->create(['category_id' => $this->category->id]);

        $response = $this->getJson("/api/v1/products?collection={$this->col1->slug}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($prodInCol->id, $ids);
        $this->assertNotContains($prodNotInCol->id, $ids);
    }

    public function test_collections_index_returns_accurate_product_count(): void
    {
        $prod1 = Product::factory()->create(['category_id' => $this->category->id]);
        $prod2 = Product::factory()->create(['category_id' => $this->category->id]);

        $prod1->collections()->sync([$this->col1->id]);
        $prod2->collections()->sync([$this->col1->id]);

        $response = $this->getJson('/api/v1/collections');

        $response->assertStatus(200);
        $colData = collect($response->json('data'))->firstWhere('id', $this->col1->id);

        $this->assertEquals(2, $colData['products_count']);
    }
}
