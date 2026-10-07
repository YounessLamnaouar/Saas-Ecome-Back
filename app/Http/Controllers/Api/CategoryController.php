<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    protected function resolveCategory($idOrSlug)
    {
        return Category::where('slug', $idOrSlug)
            ->orWhere('id', is_numeric($idOrSlug) ? $idOrSlug : 0)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $categories = Category::withCount('products')->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'image' => $cat->image,
                'products_count' => $cat->products_count,
            ];
        });

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function show($idOrSlug)
    {
        $category = $this->resolveCategory($idOrSlug);
        $category->loadCount('products');

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image,
                'products_count' => $category->products_count,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = Category::create($validated);
        $category->products_count = 0;

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, $idOrSlug)
    {
        $category = $this->resolveCategory($idOrSlug);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $category->update($validated);
        $category->loadCount('products');

        return response()->json(['data' => $category]);
    }

    public function destroy($idOrSlug)
    {
        $category = $this->resolveCategory($idOrSlug);
        $category->delete();

        return response()->json(null, 204);
    }
}
