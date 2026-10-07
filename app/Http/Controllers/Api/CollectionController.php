<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    protected function resolveCollection($idOrSlug)
    {
        return Collection::where('slug', $idOrSlug)
            ->orWhere('id', is_numeric($idOrSlug) ? $idOrSlug : 0)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $collections = Collection::withCount('products')->get()->map(function ($col) {
            return [
                'id' => $col->id,
                'name' => $col->name,
                'slug' => $col->slug,
                'description' => $col->description,
                'image' => $col->image,
                'is_featured' => $col->is_featured,
                'products_count' => $col->products_count,
            ];
        });

        return response()->json([
            'data' => $collections,
        ]);
    }

    public function show($idOrSlug)
    {
        $collection = $this->resolveCollection($idOrSlug);
        $collection->load('products');

        return response()->json([
            'data' => $collection,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:collections,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $collection = Collection::create($validated);
        $collection->products_count = 0;

        return response()->json(['data' => $collection], 201);
    }

    public function update(Request $request, $idOrSlug)
    {
        $collection = $this->resolveCollection($idOrSlug);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:collections,slug,' . $collection->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
        ]);

        $collection->update($validated);
        $collection->loadCount('products');

        return response()->json(['data' => $collection]);
    }

    public function destroy($idOrSlug)
    {
        $collection = $this->resolveCollection($idOrSlug);
        $collection->delete();

        return response()->json(null, 204);
    }
}
