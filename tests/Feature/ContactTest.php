<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContactTest extends TestCase
{
    private function message(): array
    {
        return ['name' => 'Test Customer', 'email' => 'customer@example.com', 'message' => 'Hello from the contact form'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.api_key' => 'test-key', 'services.brevo.contact_sender' => 'sender@example.com', 'services.brevo.contact_recipient' => 'owner@example.com', 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    public function test_it_sends_to_the_owner_with_customer_reply_to(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test-id'], 201)]);
        $this->postJson('/api/v1/contact', $this->message())->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request['to'][0]['email'] === 'owner@example.com'
            && $request['sender']['email'] === 'sender@example.com'
            && $request['replyTo']['email'] === 'customer@example.com'
            && str_contains($request['textContent'], 'Hello from the contact form'));
        Http::assertSentCount(1);
    }

    public function test_invalid_and_bot_submissions_do_not_send_email(): void
    {
        Http::fake();
        $this->postJson('/api/v1/contact', ['email' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/contact', [...$this->message(), 'website' => 'spam'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_provider_rejection_does_not_report_success(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'provider secret'], 403)]);
        $this->postJson('/api/v1/contact', $this->message())->assertStatus(503)->assertDontSee('provider secret');
    }

    public function test_connection_failure_is_handled(): void
    {
        Http::fake(['api.brevo.com/*' => Http::failedConnection()]);
        $this->postJson('/api/v1/contact', $this->message())->assertStatus(503);
    }

    public function test_missing_configuration_does_not_call_brevo(): void
    {
        config(['services.brevo.api_key' => '']);
        Http::fake();
        $this->postJson('/api/v1/contact', $this->message())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_requests_are_rate_limited(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test-id'], 201)]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/contact', $this->message())->assertOk();
        }
        $this->postJson('/api/v1/contact', $this->message())->assertStatus(429);
        Http::assertSentCount(5);
    }
}
