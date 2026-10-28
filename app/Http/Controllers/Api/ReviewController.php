<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    private function eligible(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)->where('status', 'livre')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))->exists();
    }
    public function index(Request $request, Product $product)
    {
        $query = $product->reviews()->with('user:id,name');
        $sort = $request->get('sort', 'newest');
        if (in_array($sort, ['highest', 'lowest'])) $query->orderBy('rating', $sort === 'highest' ? 'desc' : 'asc');
        $page = $query->latest('id')->paginate(5);
        $counts = $product->reviews()->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');
        return response()->json(['data' => [
            'average' => round((float) $product->reviews()->avg('rating'), 1),
            'count' => $product->reviews()->count(),
            'distribution' => collect(range(5, 1))->map(fn ($star) => ['rating' => $star, 'count' => (int) ($counts[$star] ?? 0)]),
            'reviews' => $page->getCollection()->map(fn ($r) => [
                'id' => $r->id, 'rating' => $r->rating, 'comment' => $r->comment,
                'author' => explode(' ', trim($r->user->name))[0], 'verified_purchase' => true,
                'created_at' => $r->created_at->toISOString(),
            ]),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }
    public function mine(Request $request, Product $product)
    {
        return response()->json(['data' => ['eligible' => $this->eligible($request->user()->id, $product->id),
            'review' => $product->reviews()->where('user_id', $request->user()->id)->first(['rating', 'comment'])]]);
    }
    public function store(Request $request, Product $product)
    {
        $data = $request->validate(['rating' => ['required', 'integer', Rule::in([1,2,3,4,5])], 'comment' => ['nullable', 'string', 'max:2000']]);
        abort_unless($this->eligible($request->user()->id, $product->id), 403, 'A delivered purchase is required to review this product.');
        // Serialize edits on the product; the unique constraint prevents duplicate reviews.
        DB::transaction(function () use ($product, $request, $data) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            Review::updateOrCreate(['product_id' => $product->id, 'user_id' => $request->user()->id], $data);
        });
        return response()->json(['message' => 'Review saved.']);
    }
    public function destroy(Request $request, Product $product)
    {
        $product->reviews()->where('user_id', $request->user()->id)->delete();
        return response()->noContent();
    }
}
