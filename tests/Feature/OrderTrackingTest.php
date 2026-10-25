<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer1;

    protected User $customer2;

    protected Category $category;

    protected Product $product1;

    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->customer1 = User::factory()->create(['is_admin' => false]);
        $this->customer2 = User::factory()->create(['is_admin' => false]);

        $this->category = Category::factory()->create(['slug' => 'test-cat']);

        $this->product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Silk Dress',
            'price' => 100.00,
            'discount_percentage' => 20, // Discounted: 80.00
            'stock' => 10,
        ]);

        $this->product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Cotton Shirt',
            'price' => 50.00,
            'discount_percentage' => 0, // 50.00
            'stock' => 5,
        ]);
    }

    public function test_can_create_order_with_server_side_total_and_stock_deduction(): void
    {
        Sanctum::actingAs($this->customer1);

        $payload = [
            'customer' => [
                'name' => 'Alice Martin',
                'phone' => '+212600000000',
                'address' => '123 Main St',
                'city' => 'Casablanca',
                'notes' => 'Please ring the doorbell',
            ],
            'items' => [
                ['product_id' => $this->product1->id, 'quantity' => 2], // 2 * 80.00 = 160.00
                ['product_id' => $this->product2->id, 'quantity' => 1], // 1 * 50.00 = 50.00
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer.name', 'Alice Martin')
            ->assertJsonPath('data.status', 'en_attente');

        $this->assertEquals(210.00, (float) $response->json('data.total'));

        $code = $response->json('data.code');
        $this->assertStringStartsWith('CMD-', $code);

        // Verify stock deducted
        $this->assertEquals(8, $this->product1->fresh()->stock);
        $this->assertEquals(4, $this->product2->fresh()->stock);

        // Verify database records
        $order = Order::where('code', $code)->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->customer1->id, $order->user_id);
        $this->assertCount(2, $order->items);
        $this->assertCount(1, $order->histories);
        $this->assertEquals(OrderStatus::EN_ATTENTE, $order->histories->first()->status);
    }

    public function test_order_creation_fails_with_422_when_stock_is_insufficient(): void
    {
        Sanctum::actingAs($this->customer1);
        $payload = [
            'customer' => [
                'name' => 'Bob Marley',
                'phone' => '0612345678',
                'address' => '456 High St',
                'city' => 'Rabat',
            ],
            'items' => [
                ['product_id' => $this->product2->id, 'quantity' => 10], // Product2 has only 5 in stock
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $payload);

        $response->assertStatus(422)
            ->assertSee('Cotton Shirt');

        // Stock was not decremented
        $this->assertEquals(5, $this->product2->fresh()->stock);
    }

    public function test_order_tracking_with_code_and_phone(): void
    {
        $order = Order::create([
            'code' => 'CMD-20261018-TEST',
            'customer_name' => 'Samir B.',
            'customer_phone' => '+212611223344',
            'customer_address' => '78 Boulevard Anfa',
            'customer_city' => 'Casablanca',
            'total' => 150.00,
            'status' => OrderStatus::EN_ATTENTE,
        ]);
        $order->items()->create([
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->title,
            'unit_price' => 80.00,
            'quantity' => 1,
        ]);
        $order->histories()->create([
            'status' => OrderStatus::EN_ATTENTE,
            'created_at' => now(),
        ]);

        // Wrong phone -> 404
        $resWrongPhone = $this->getJson("/api/v1/orders/track?code={$order->code}&phone=0699999999");
        $resWrongPhone->assertStatus(404);

        // Wrong code -> 404
        $resWrongCode = $this->getJson('/api/v1/orders/track?code=CMD-NONEXISTENT&phone=+212611223344');
        $resWrongCode->assertStatus(404);

        // Correct code & matching phone -> 200
        $resOk = $this->getJson("/api/v1/orders/track?code={$order->code}&phone=+212611223344");
        $resOk->assertStatus(200)
            ->assertJsonPath('data.code', $order->code)
            ->assertJsonPath('data.customer.name', 'Samir B.');
    }

    public function test_admin_can_update_status_forward_only(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::create([
            'code' => 'CMD-STATUS-TEST',
            'customer_name' => 'Karim',
            'customer_phone' => '0600000000',
            'customer_address' => 'Marrakech',
            'customer_city' => 'Marrakech',
            'total' => 100.00,
            'status' => OrderStatus::EN_ATTENTE,
        ]);
        $order->histories()->create([
            'status' => OrderStatus::EN_ATTENTE,
            'created_at' => now(),
        ]);

        // Move forward: en_attente -> confirmee
        $resForward = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'confirmee',
        ]);
        $resForward->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmee');

        $this->assertEquals(OrderStatus::CONFIRMEE, $order->fresh()->status);
        $this->assertCount(2, $order->fresh()->histories);

        // Skip ahead: confirmee -> livre
        $resSkip = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'livre',
        ]);
        $resSkip->assertStatus(200)
            ->assertJsonPath('data.status', 'livre');

        // Move backward: livre -> en_cours_de_livraison -> should fail with 422
        $resBackward = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'en_cours_de_livraison',
        ]);
        $resBackward->assertStatus(422);

        // Same status -> should fail with 422
        $resSame = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'livre',
        ]);
        $resSame->assertStatus(422);
    }

    public function test_customer_cannot_see_another_customers_order(): void
    {
        $orderOfCustomer1 = Order::create([
            'code' => 'CMD-USER1-ORDER',
            'user_id' => $this->customer1->id,
            'customer_name' => 'Customer 1',
            'customer_phone' => '0611111111',
            'customer_address' => 'Street 1',
            'customer_city' => 'Fes',
            'total' => 80.00,
            'status' => OrderStatus::EN_ATTENTE,
        ]);

        // Customer 2 tries to access Customer 1's order
        Sanctum::actingAs($this->customer2);

        $response = $this->getJson("/api/v1/my-orders/{$orderOfCustomer1->code}");
        $response->assertStatus(404);

        // Customer 2 my-orders list does not include Customer 1's order
        $listResponse = $this->getJson('/api/v1/my-orders');
        $listResponse->assertStatus(200);
        $codes = collect($listResponse->json('data'))->pluck('code')->toArray();
        $this->assertNotContains($orderOfCustomer1->code, $codes);

        // Customer 1 accessing own order succeeds
        Sanctum::actingAs($this->customer1);
        $okResponse = $this->getJson("/api/v1/my-orders/{$orderOfCustomer1->code}");
        $okResponse->assertStatus(200)
            ->assertJsonPath('data.code', $orderOfCustomer1->code);
    }
}
