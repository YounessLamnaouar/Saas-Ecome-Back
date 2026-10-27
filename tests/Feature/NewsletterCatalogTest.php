<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsletterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_requires_configuration_and_valid_email(): void
    {
        Http::preventStrayRequests();
        config(['services.brevo.api_key' => null]);
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'test@example.com'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_newsletter_subscribes_to_configured_list_and_reports_provider_errors(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::sequence()->push([], 404)->push(['id' => 1], 201)->push(['listIds' => [7]])->push([], 401)]);
        foreach ([200, 200, 503] as $status) {
            $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'Test@example.com'])->assertStatus($status);
        }
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['email'] === 'test@example.com' && $r['listIds'] === [7] && ! isset($r['updateEnabled']) && $r->hasHeader('api-key', 'test-key'));
    }

    public function test_existing_contact_is_added_using_list_endpoint(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::sequence()
            ->push(['listIds' => [2]])
            ->push(['contacts' => ['success' => ['test@example.com'], 'failure' => []]], 201)]);
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'Test@example.com'])->assertOk();
        Http::assertSent(fn ($r) => $r->url() === 'https://api.brevo.com/v3/contacts/lists/7/contacts/add' && $r['emails'] === ['test@example.com']);
        Http::assertSentCount(2);
    }

    public function test_failed_list_membership_does_not_report_success(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::sequence()
            ->push(['listIds' => [2]])
            ->push(['contacts' => ['success' => [], 'failure' => ['test@example.com']]], 201)]);
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'test@example.com'])->assertStatus(503);
    }

    public function test_temporary_provider_error_is_retried(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::sequence()->push([], 504)->push(['listIds' => [7]])]);
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'test@example.com'])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_read_only_brevo_diagnostic_checks_list_access(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::sequence()->push(['id' => 7])->push([], 401)->push([], 404)]);
        $this->artisan('brevo:check-newsletter')->assertSuccessful();
        $this->artisan('brevo:check-newsletter')->assertFailed();
        $this->artisan('brevo:check-newsletter')->assertFailed();
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->url() === 'https://api.brevo.com/v3/contacts/lists/7');
    }

    public function test_brevo_diagnostic_reports_safe_provider_reason(): void
    {
        config(['services.brevo.api_key' => 'private-test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'permission_denied', 'message' => 'Access denied for private-test-key'], 403)]);
        $this->artisan('brevo:check-newsletter')
            ->expectsOutputToContain('Provider code: permission_denied')
            ->expectsOutputToContain('Provider message: Access denied for [REDACTED]')
            ->assertFailed();
    }

    public function test_brevo_diagnostic_identifies_non_json_denial(): void
    {
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.newsletter_list_id' => 7]);
        Http::fake(['api.brevo.com/*' => Http::response('Forbidden', 403, ['Content-Type' => 'text/plain'])]);
        $this->artisan('brevo:check-newsletter')
            ->expectsOutputToContain('No Brevo JSON error message was returned.')
            ->assertFailed();
    }

    public function test_catalog_import_is_idempotent_and_preserves_stock(): void
    {
        Http::fake(['dummyjson.com/*' => Http::response(['products' => [[
            'id' => 1, 'title' => 'Test product', 'category' => 'beauty', 'description' => 'Demo', 'price' => 25,
            'stock' => 12, 'thumbnail' => 'https://cdn.dummyjson.com/product-images/beauty/test/thumbnail.webp',
            'images' => ['https://cdn.dummyjson.com/product-images/beauty/test/1.webp'],
        ]]])]);
        $this->artisan('catalog:import-dummyjson')->assertSuccessful();
        $product = Product::firstOrFail();
        $this->assertCount(1, $product->images);
        $product->update(['stock' => 3]);
        $this->artisan('catalog:import-dummyjson')->assertSuccessful();
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(3, (int) $product->fresh()->stock);
    }
}
