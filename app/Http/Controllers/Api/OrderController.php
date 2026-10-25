<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->customer_address,
                'city' => $order->customer_city,
                'notes' => $order->notes,
            ],
            'user' => $order->relationLoaded('user') && $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'price' => (float) $item->unit_price,
                'qty' => (int) $item->quantity,
                'color' => $item->color,
            ])->values()->toArray() : [],
            'history' => $order->relationLoaded('histories') ? $order->histories->map(fn ($h) => [
                'status' => $h->status instanceof OrderStatus ? $h->status->value : $h->status,
                'created_at' => $h->created_at?->toISOString(),
                'changed_by' => $h->relationLoaded('changer') && $h->changer ? $h->changer->name : null,
            ])->values()->toArray() : [],
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.color' => 'nullable|string',
            'customer' => 'nullable|array',
            'customer.name' => 'nullable|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
            'customer.address' => 'nullable|string|max:500',
            'customer.city' => 'nullable|string|max:100',
            'customer.notes' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:500',
            'customer_city' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $name = $request->input('customer.name') ?? $request->input('customer_name') ?? $request->input('name');
        $phone = $request->input('customer.phone') ?? $request->input('customer_phone') ?? $request->input('phone');
        $address = $request->input('customer.address') ?? $request->input('customer_address') ?? $request->input('address');
        $city = $request->input('customer.city') ?? $request->input('customer_city') ?? $request->input('city');
        $notes = $request->input('customer.notes') ?? $request->input('notes');

        $missing = [];
        if (empty($name)) $missing[] = 'customer_name';
        if (empty($phone)) $missing[] = 'customer_phone';
        if (empty($address)) $missing[] = 'customer_address';
        if (empty($city)) $missing[] = 'customer_city';

        if (! empty($missing)) {
            return response()->json([
                'message' => 'Customer name, phone, address, and city are required.',
                'errors' => collect($missing)->mapWithKeys(fn ($k) => [$k => ["The {$k} field is required."]])->toArray(),
            ], 422);
        }

        $userId = auth('sanctum')->check() ? auth('sanctum')->id() : null;

        $createdOrder = DB::transaction(function () use ($validated, $name, $phone, $address, $city, $notes, $userId) {
            $insufficient = [];
            $products = [];

            foreach ($validated['items'] as $item) {
                $p = Product::lockForUpdate()->find($item['product_id']);
                if (! $p || $p->stock < (int) $item['quantity']) {
                    $insufficient[] = $p ? $p->title : "Product #{$item['product_id']}";
                }
                $products[$item['product_id']] = $p;
            }

            if (! empty($insufficient)) {
                abort(response()->json([
                    'message' => 'Insufficient stock for product(s): ' . implode(', ', $insufficient),
                    'errors' => [
                        'items' => ['Insufficient stock for: ' . implode(', ', $insufficient)],
                    ],
                ], 422));
            }

            $total = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products[$item['product_id']];
                $discount = (float) ($product->discount_percentage ?? 0);
                $price = (float) $product->price;
                $unitPrice = $discount > 0 ? round($price * (1 - $discount / 100), 2) : $price;
                $qty = (int) $item['quantity'];

                $total += $unitPrice * $qty;
                $product->decrement('stock', $qty);

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'unit_price' => $unitPrice,
                    'quantity' => $qty,
                    'color' => $item['color'] ?? null,
                ];
            }

            do {
                $code = 'CMD-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            } while (Order::where('code', $code)->exists());

            $order = Order::create([
                'code' => $code,
                'user_id' => $userId,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_address' => $address,
                'customer_city' => $city,
                'notes' => $notes,
                'total' => round($total, 2),
                'payment_method' => 'cod',
                'status' => OrderStatus::EN_ATTENTE,
            ]);

            foreach ($itemsData as $row) {
                $order->items()->create($row);
            }

            $order->histories()->create([
                'status' => OrderStatus::EN_ATTENTE,
                'changed_by' => $userId,
                'created_at' => now(),
            ]);

            return $order;
        });

        $createdOrder->load(['items', 'histories', 'user']);

        return response()->json([
            'message' => 'Order placed successfully.',
            'data' => $this->formatOrder($createdOrder),
        ], 201);
    }

    public function track(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'phone' => 'required|string',
        ]);

        $order = Order::with(['items', 'histories'])->where('code', trim($request->code))->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found with provided code and phone number.'], 404);
        }

        $cleanReqPhone = preg_replace('/[^\d]/', '', $request->phone);
        $cleanOrderPhone = preg_replace('/[^\d]/', '', $order->customer_phone);

        if ($cleanReqPhone !== $cleanOrderPhone && trim($request->phone) !== trim($order->customer_phone)) {
            return response()->json(['message' => 'Order not found with provided code and phone number.'], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    public function myOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['items', 'histories'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders->map(fn ($o) => $this->formatOrder($o)),
        ]);
    }

    public function myOrder(Request $request, string $code)
    {
        $user = $request->user();

        $order = Order::with(['items', 'histories'])
            ->where('code', $code)
            ->where('user_id', $user->id)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Order::with(['items', 'histories', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        $paginator = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($o) => $this->formatOrder($o)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function adminShow(int|string $id)
    {
        $order = Order::with(['items', 'histories.changer', 'user'])->findOrFail($id);

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    public function adminUpdateStatus(Request $request, int|string $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $order = Order::findOrFail($id);
        $newStatus = OrderStatus::from($validated['status']);

        if (! $order->status->canTransitionTo($newStatus)) {
            return response()->json([
                'message' => 'Cannot transition order status backwards or to the same status.',
                'errors' => [
                    'status' => ['Transitions are forward-only (en_attente -> confirmee -> en_cours_de_livraison -> livre).'],
                ],
            ], 422);
        }

        $order->update(['status' => $newStatus]);

        $order->histories()->create([
            'status' => $newStatus,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $order->load(['items', 'histories.changer', 'user']);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'data' => $this->formatOrder($order),
        ]);
    }
}
