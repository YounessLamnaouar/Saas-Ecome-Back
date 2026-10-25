<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_place_order(): void
    {
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_reset_token_is_single_use_and_revokes_api_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('existing');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'];
        $this->postJson('/api/v1/auth/reset-password', array_merge($payload, ['password_confirmation' => 'different']))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('New-password-123', $user->fresh()->password));
        $this->assertEquals(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_password_request_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->assertJsonPath('message', 'If this email has an account, a reset link has been sent.');
        Notification::assertNothingSent();
    }

    public function test_status_emails_and_localized_labels(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $order = Order::create(['code' => 'CMD-MAIL', 'user_id' => $customer->id, 'customer_name' => 'Customer', 'customer_phone' => '0600000000', 'customer_address' => 'Street', 'customer_city' => 'City', 'total' => 10, 'status' => OrderStatus::EN_ATTENTE]);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmee'])->assertOk()->assertJsonPath('data.status_label', 'Confirmed');
        Notification::assertSentTo($customer, OrderStatusChanged::class, fn ($n) => $n->status === 'confirmee' && str_contains($n->toMail($customer)->subject, 'Confirmed'));
        $this->withHeader('Accept-Language', 'fr')->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'en_cours_de_livraison'])->assertOk()->assertJsonPath('data.status_label', 'En cours de livraison');
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'en_cours_de_livraison'])->assertUnprocessable();
        Notification::assertSentToTimes($customer, OrderStatusChanged::class, 2);
    }
}
