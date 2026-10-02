<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected function formatProduct($product)
    {
        $price = (float) $product->price;
        $discount = (float) ($product->discount_percentage ?? 0);
        $discountedPrice = $discount > 0 ? round($price * (1 - $discount / 100), 2) : $price;

        return [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku ?? '',
            'description' => $product->description ?? '',
            'price' => $price,
            'discount_percentage' => $discount,
            'discounted_price' => $discountedPrice,
            'thumbnail' => $product->thumbnail ?? '',
            'images' => $product->relationLoaded('images') && $product->images ? $product->images->pluck('url')->toArray() : [],
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'brand' => $product->brand ?? '',
            'rating' => (float) ($product->rating ?? 0),
            'stock' => (int) ($product->stock ?? 0),
            'availability' => ($product->stock ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
            'tags' => $product->relationLoaded('tags') && $product->tags ? $product->tags->pluck('name')->toArray() : [],
            'created_at' => $product->created_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'tags', 'images']);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $dir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $sortBy = $request->sort_by;
            if ($sortBy === 'name') {
                $sortBy = 'title';
            }
            $query->orderBy($sortBy, $dir);
        } else {
            $query->latest();
        }

        $perPage = (int) $request->get('per_page', 12);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($p) => $this->formatProduct($p));

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'tags', 'images']);

        return response()->json([
            'data' => $this->formatProduct($product),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'brand' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'stock' => 'nullable|integer|min:0',
            'category_slug' => 'nullable|string',
            'thumbnail' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $categoryId = null;
        if (! empty($validated['category_slug'])) {
            $category = Category::where('slug', $validated['category_slug'])->first();
            if ($category) {
                $categoryId = $category->id;
            }
        }

        $product = Product::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(5),
            'description' => $validated['description'] ?? '',
            'price' => $validated['price'],
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'brand' => $validated['brand'] ?? '',
            'sku' => $validated['sku'] ?? strtoupper(Str::random(8)),
            'stock' => $validated['stock'] ?? 0,
            'category_id' => $categoryId,
            'thumbnail' => $validated['thumbnail'] ?? 'https://picsum.photos/seed/' . Str::random(6) . '/600/600',
            'rating' => 5,
        ]);

        if (! empty($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName],
                    ['slug' => Str::slug($tagName)]
                );
                $tagIds[] = $tag->id;
            }
            $product->tags()->sync($tagIds);
        }

        $product->load(['category', 'tags', 'images']);

        return response()->json([
            'data' => $this->formatProduct($product),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'brand' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'stock' => 'nullable|integer|min:0',
            'category_slug' => 'nullable|string',
            'thumbnail' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        if (array_key_exists('category_slug', $validated)) {
            if (! empty($validated['category_slug'])) {
                $category = Category::where('slug', $validated['category_slug'])->first();
                $validated['category_id'] = $category ? $category->id : null;
            } else {
                $validated['category_id'] = null;
            }
            unset($validated['category_slug']);
        }

        $product->update($validated);

        if (isset($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName],
                    ['slug' => Str::slug($tagName)]
                );
                $tagIds[] = $tag->id;
            }
            $product->tags()->sync($tagIds);
        }

        $product->load(['category', 'tags', 'images']);

        return response()->json([
            'data' => $this->formatProduct($product),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
