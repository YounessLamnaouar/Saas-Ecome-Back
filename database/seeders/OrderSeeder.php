<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        if ($products->isEmpty()) {
            return;
        }

        $admin = User::where('is_admin', true)->first();

        $demoOrders = [
            [
                'customer_name' => 'Fatima Zahra',
                'customer_phone' => '+212661234567',
                'customer_address' => '15 Rue de la Liberté, Maarif',
                'customer_city' => 'Casablanca',
                'notes' => 'Call before arrival please.',
                'status' => OrderStatus::EN_ATTENTE,
                'items_count' => 2,
            ],
            [
                'customer_name' => 'Youssef El Amrani',
                'customer_phone' => '+212672345678',
                'customer_address' => '42 Avenue Mohammed V',
                'customer_city' => 'Rabat',
                'notes' => 'Leave with the doorman if absent.',
                'status' => OrderStatus::CONFIRMEE,
                'items_count' => 3,
            ],
            [
                'customer_name' => 'Salma Bennani',
                'customer_phone' => '+212683456789',
                'customer_address' => '10 Rue Ibn Battouta, Guéliz',
                'customer_city' => 'Marrakech',
                'notes' => null,
                'status' => OrderStatus::EN_COURS_DE_LIVRAISON,
                'items_count' => 1,
            ],
            [
                'customer_name' => 'Mehdi Chraibi',
                'customer_phone' => '+212694567890',
                'customer_address' => '88 Boulevard Hassan II',
                'customer_city' => 'Fès',
                'notes' => 'Cash ready.',
                'status' => OrderStatus::LIVRE,
                'items_count' => 2,
            ],
        ];

        foreach ($demoOrders as $index => $data) {
            $code = 'CMD-' . date('Ymd') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $selectedProducts = $products->random(min($data['items_count'], $products->count()));

            $total = 0;
            $itemsData = [];

            foreach ($selectedProducts as $prod) {
                $discount = (float) ($prod->discount_percentage ?? 0);
                $unitPrice = $discount > 0 ? round($prod->price * (1 - $discount / 100), 2) : (float) $prod->price;
                $qty = random_int(1, 2);
                $total += $unitPrice * $qty;

                $itemsData[] = [
                    'product_id' => $prod->id,
                    'product_name' => $prod->title,
                    'unit_price' => $unitPrice,
                    'quantity' => $qty,
                    'color' => null,
                ];
            }

            $order = Order::create([
                'code' => $code,
                'user_id' => null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_address' => $data['customer_address'],
                'customer_city' => $data['customer_city'],
                'notes' => $data['notes'],
                'total' => round($total, 2),
                'payment_method' => 'cod',
                'status' => $data['status'],
                'created_at' => now()->subDays(4 - $index),
                'updated_at' => now()->subDays(4 - $index),
            ]);

            foreach ($itemsData as $row) {
                $order->items()->create($row);
            }

            // Create status history based on current status
            $order->histories()->create([
                'status' => OrderStatus::EN_ATTENTE,
                'changed_by' => null,
                'created_at' => now()->subDays(4 - $index),
            ]);

            if ($data['status'] === OrderStatus::CONFIRMEE || $data['status'] === OrderStatus::EN_COURS_DE_LIVRAISON || $data['status'] === OrderStatus::LIVRE) {
                $order->histories()->create([
                    'status' => OrderStatus::CONFIRMEE,
                    'changed_by' => $admin?->id,
                    'created_at' => now()->subDays(3 - $index),
                ]);
            }

            if ($data['status'] === OrderStatus::EN_COURS_DE_LIVRAISON || $data['status'] === OrderStatus::LIVRE) {
                $order->histories()->create([
                    'status' => OrderStatus::EN_COURS_DE_LIVRAISON,
                    'changed_by' => $admin?->id,
                    'created_at' => now()->subDays(2 - $index),
                ]);
            }

            if ($data['status'] === OrderStatus::LIVRE) {
                $order->histories()->create([
                    'status' => OrderStatus::LIVRE,
                    'changed_by' => $admin?->id,
                    'created_at' => now()->subDays(1 - $index),
                ]);
            }
        }
    }
}
