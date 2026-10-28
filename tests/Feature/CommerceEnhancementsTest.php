<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StoreOrderEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(User $user, Product $product, string $status = 'livre'): Order
    {
        $order = Order::create(['code' => 'TEST-'.uniqid(), 'user_id' => $user->id, 'customer_name' => 'Buyer', 'customer_phone' => '0600000000', 'customer_address' => 'Street', 'customer_city' => 'City', 'total' => 25, 'payment_method' => 'cod', 'status' => $status]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->title, 'unit_price' => 25, 'quantity' => 1]);
        return $order;
    }

    public function test_reviews_require_delivery_and_replace_instead_of_duplicate(): void
    {
        $product = Product::factory()->create(['rating' => 5]);
        $buyer = User::factory()->create();
        $this->getJson('/api/v1/products/'.$product->id)->assertJsonPath('data.rating', 0)->assertJsonPath('data.review_count', 0);
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])->assertUnauthorized();
        Sanctum::actingAs($buyer);
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])->assertForbidden();
        $order = $this->purchase($buyer, $product, 'en_attente');
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])->assertForbidden();
        $order->update(['status' => 'livre']);
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 6])->assertUnprocessable();
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5, 'comment' => 'Excellent'])->assertOk();
        $this->putJson("/api/v1/products/{$product->id}/reviews", ['rating' => 3, 'comment' => 'Updated'])->assertOk();
        $this->assertDatabaseCount('reviews', 1);
        $this->getJson("/api/v1/products/{$product->id}")->assertJsonPath('data.rating', 3)->assertJsonPath('data.review_count', 1);
        $this->getJson("/api/v1/products/{$product->id}/reviews")->assertJsonPath('data.count', 1)->assertJsonPath('data.reviews.0.verified_purchase', true)->assertJsonPath('data.distribution.2.count', 1);
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/products/{$product->id}/reviews")->assertNoContent();
        $this->assertDatabaseCount('reviews', 1);
        Sanctum::actingAs($buyer);
        $this->deleteJson("/api/v1/products/{$product->id}/reviews")->assertNoContent();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_dashboard_is_admin_only_and_counts_entire_catalog(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(105)->create(['category_id' => $category->id, 'price' => 10, 'stock' => 2]);
        $product = Product::first();
        $buyer = User::factory()->create();
        $this->purchase($buyer, $product);
        $this->purchase($buyer, $product, 'en_attente');
        Collection::create(['name' => 'Actual Collection', 'slug' => 'actual']);
        $this->getJson('/api/v1/admin/stats')->assertUnauthorized();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/admin/stats')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson('/api/v1/admin/stats')->assertOk()->assertJsonPath('data.totalProducts', 105)
            ->assertJsonPath('data.totalCollections', 1)->assertJsonPath('data.lowStock', 105)
            ->assertJsonPath('data.pendingOrders', 1)->assertJsonPath('data.deliveredRevenue', 25)
            ->assertJsonPath('data.inventoryValue', 2100)->assertJsonCount(30, 'data.trend');
    }

    public function test_order_is_retained_if_email_fails_and_can_be_retried_without_resending_success(): void
    {
        Http::preventStrayRequests();
        config(['services.brevo.api_key' => 'test-key']);
        Http::fake(['api.brevo.com/*' => Http::sequence()->push([], 503)->push(['messageId' => 'mail-1'], 201)]);
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock' => 5, 'price' => 25, 'discount_percentage' => 0]);
        $result = $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'customer' => ['name' => 'Buyer', 'phone' => '0600000000', 'address' => 'Test Street', 'city' => 'Test City']])->assertCreated();
        $id = $result->json('data.id');
        $this->assertDatabaseHas('order_email_deliveries', ['order_id' => $id, 'sent_at' => null, 'attempts' => 1]);
        $this->assertEquals(3, $product->fresh()->stock);
        app(StoreOrderEmail::class)->send($id);
        $this->assertNotNull(DB::table('order_email_deliveries')->where('order_id', $id)->value('sent_at'));
        app(StoreOrderEmail::class)->send($id);
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['to'][0]['email'] === 'lamnaouaryouness231@gmail.com'
            && str_contains($r['textContent'], '2 x '.$product->title)
            && str_contains($r['textContent'], '50.00')
            && $r['subject'] === 'New order '.$result->json('data.code').' | Fashion Store');
    }

    public function test_insufficient_stock_does_not_create_an_email_delivery(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock' => 0]);
        $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'customer' => ['name' => 'Buyer', 'phone' => '0600000000', 'address' => 'Street', 'city' => 'City']])->assertUnprocessable();
        $this->assertDatabaseCount('order_email_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_provider_idempotency_response_marks_notification_accepted(): void
    {
        config(['services.brevo.api_key' => 'test-key']);
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'duplicate_parameter', 'message' => 'Email already processed for this idempotency key'], 400)]);
        $order = $this->purchase(User::factory()->create(), Product::factory()->create());
        DB::table('order_email_deliveries')->insert(['order_id' => $order->id, 'attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);
        app(StoreOrderEmail::class)->send($order->id);
        $this->assertNotNull(DB::table('order_email_deliveries')->where('order_id', $order->id)->value('sent_at'));
        Http::assertSent(fn ($request) => \Ramsey\Uuid\Uuid::isValid($request['headers']['idempotencyKey']));
    }

    public function test_permission_failure_is_visible_to_admin_and_cleared_after_retry(): void
    {
        config(['services.brevo.api_key' => 'test-key']);
        Http::fake(['api.brevo.com/*' => Http::sequence()
            ->push(['code' => 'permission_denied'], 403)
            ->push(['messageId' => 'accepted'], 201)]);
        $order = $this->purchase(User::factory()->create(), Product::factory()->create());
        DB::table('order_email_deliveries')->insert(['order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
        app(StoreOrderEmail::class)->send($order->id);
        $this->assertDatabaseHas('order_email_deliveries', ['order_id' => $order->id, 'last_error' => 'brevo_permission_denied', 'sent_at' => null]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson('/api/v1/admin/stats')->assertOk()->assertJsonPath('data.emailPermissionDenied', true);
        app(StoreOrderEmail::class)->send($order->id);
        $this->getJson('/api/v1/admin/stats')->assertOk()->assertJsonPath('data.emailPermissionDenied', false)->assertJsonPath('data.pendingEmails', 0);
        $this->assertDatabaseHas('order_email_deliveries', ['order_id' => $order->id, 'last_error' => null]);
    }
}
