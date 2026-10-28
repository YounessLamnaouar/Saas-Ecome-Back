<?php
namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreOrderEmail
{
    public function send(int $orderId): void
    {
        try {
            Cache::lock('store-order-email-'.$orderId, 60)->get(function () use ($orderId) {
                $delivery = DB::table('order_email_deliveries')->where('order_id', $orderId)->first();
                if (! $delivery || $delivery->sent_at) return;
                DB::table('order_email_deliveries')->where('order_id', $orderId)->update(['attempts' => $delivery->attempts + 1, 'attempted_at' => now(), 'updated_at' => now()]);
                $order = Order::with(['items', 'user'])->findOrFail($orderId);
                $items = $order->items->map(fn ($item) => $item->quantity.' x '.$item->product_name.' @ $'.number_format($item->unit_price, 2).($item->color ? ' ('.$item->color.')' : ''))->implode("\n");
                $payload = [
                    'sender' => ['email' => config('services.brevo.contact_sender'), 'name' => config('services.brevo.contact_sender_name')],
                    'to' => [['email' => config('services.brevo.contact_recipient')]],
                    'subject' => 'New order '.$order->code.' | Fashion Store',
                    'textContent' => "Order: {$order->code}\nCustomer: {$order->customer_name}\nEmail: ".($order->user?->email ?? '')."\nPhone: {$order->customer_phone}\nAddress: {$order->customer_address}, {$order->customer_city}\n\n{$items}\n\nTotal: $".number_format($order->total, 2)."\nPayment: Cash on delivery\nNotes: {$order->notes}",
                    'headers' => ['idempotencyKey' => (string) \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, config('app.url').'/store-order/'.$order->code)],
                    'tags' => ['store-order'],
                ];
                if ($order->user?->email) $payload['replyTo'] = ['email' => $order->user->email];
                $key = trim((string) config('services.brevo.api_key'));
                if (! $key) {
                    $this->recordFailure($orderId, 'missing_api_key');
                    return;
                }
                $response = Http::withHeaders(['api-key' => $key])->acceptJson()->connectTimeout(5)->timeout(10)
                    ->post('https://api.brevo.com/v3/smtp/email', $payload);
                $alreadyAccepted = $response->status() === 400
                    && $response->json('code') === 'duplicate_parameter'
                    && str_contains(strtolower((string) $response->json('message')), 'idempotency');
                if (($response->successful() && $response->json('messageId')) || $alreadyAccepted) {
                    DB::table('order_email_deliveries')->where('order_id', $orderId)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                } else {
                    $error = $response->status() === 403 && $response->json('code') === 'permission_denied'
                        ? 'brevo_permission_denied' : 'brevo_http_'.$response->status();
                    $this->recordFailure($orderId, $error);
                }
            });
        } catch (Throwable $e) {
            try { $this->recordFailure($orderId, 'delivery_exception'); } catch (Throwable) { /* Preserve checkout if storage is unavailable. */ }
            Log::warning('Store order email pending retry.', ['order_id' => $orderId, 'exception' => get_class($e)]);
        }
    }

    private function recordFailure(int $orderId, string $error): void
    {
        DB::table('order_email_deliveries')->where('order_id', $orderId)->update(['last_error' => $error, 'updated_at' => now()]);
        Log::warning('Store order email pending retry.', ['order_id' => $orderId, 'error' => $error]);
    }
}
