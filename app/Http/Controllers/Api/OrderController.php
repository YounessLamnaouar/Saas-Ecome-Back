<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer' => 'nullable|array',
        ]);

        $updatedProducts = [];

        DB::transaction(function () use ($validated, &$updatedProducts) {
            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);
                if ($product && $product->stock > 0) {
                    $deductQty = min($product->stock, (int) $item['quantity']);
                    $product->decrement('stock', $deductQty);
                    $product->refresh();
                    $updatedProducts[] = [
                        'id' => $product->id,
                        'remaining_stock' => $product->stock,
                    ];
                }
            }
        });

        return response()->json([
            'message' => 'Order created successfully and stock updated.',
            'data' => [
                'updated_products' => $updatedProducts,
            ],
        ], 201);
    }
}
