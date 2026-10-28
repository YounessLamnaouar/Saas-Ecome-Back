<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_a_private_pdf_available_only_to_owner_or_admin(): void
    {
        $owner = User::factory()->create();
        $order = Order::create(['code' => 'CMD-INVOICE', 'user_id' => $owner->id, 'customer_name' => 'Buyer', 'customer_phone' => '0600000000', 'customer_address' => 'Street', 'customer_city' => 'City', 'total' => 39.90, 'status' => 'en_attente', 'payment_method' => 'cod']);
        $order->items()->create(['product_id' => null, 'product_name' => 'Saved product <script>name</script>', 'unit_price' => 19.95, 'quantity' => 2]);
        $url = '/api/v1/my-orders/'.$order->code.'/invoice';
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/v1/admin/orders/'.$order->id.'/invoice')->assertForbidden();
        Sanctum::actingAs($owner);
        $result = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Invoice-CMD-INVOICE.pdf"');
        $this->assertStringStartsWith('%PDF-', $result->getContent());
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $html = view('invoices.order', ['order' => $order->load('items')])->render();
        $this->assertStringContainsString('$39.90', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/api/v1/admin/orders/'.$order->id.'/invoice')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
