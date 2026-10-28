<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $start = now()->startOfDay()->subDays(29);
        $daily = Order::where('created_at', '>=', $start)->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as value')->groupByRaw('DATE(created_at)')->get()->keyBy('day');
        $trend = collect(range(0, 29))->map(function ($offset) use ($start, $daily) {
            $date = $start->copy()->addDays($offset)->toDateString();
            return ['date' => $date, 'orders' => (int) ($daily[$date]->orders ?? 0), 'value' => round((float) ($daily[$date]->value ?? 0), 2)];
        });
        return response()->json(['data' => [
            'totalProducts' => Product::count(), 'totalCategories' => Category::count(), 'totalCollections' => Collection::count(),
            'totalOrders' => Order::count(), 'pendingOrders' => Order::where('status', 'en_attente')->count(),
            'deliveredRevenue' => (float) Order::where('status', 'livre')->sum('total'),
            'openOrderValue' => (float) Order::where('status', '!=', 'livre')->sum('total'),
            'lowStock' => Product::whereBetween('stock', [1, 20])->count(), 'outOfStock' => Product::where('stock', '<=', 0)->count(),
            'inventoryValue' => round((float) Product::selectRaw('SUM(price * stock) as value')->value('value'), 2),
            'avgRating' => round((float) Review::avg('rating'), 1), 'reviewCount' => Review::count(),
            'pendingEmails' => DB::table('order_email_deliveries')->whereNull('sent_at')->count(),
            'emailPermissionDenied' => DB::table('order_email_deliveries')->whereNull('sent_at')->where('last_error', 'brevo_permission_denied')->exists(),
            'productsPerCategory' => Category::withCount('products')->orderByDesc('products_count')->get()->map(fn ($c) => ['name' => $c->name, 'count' => $c->products_count]),
            'collections' => Collection::withCount('products')->orderByDesc('products_count')->limit(6)->get(['id', 'name', 'slug']),
            'trend' => $trend,
            'orderStatuses' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
            'recentOrders' => Order::latest('id')->limit(6)->get(['id', 'code', 'customer_name', 'total', 'status', 'created_at']),
            'stockAlerts' => Product::where('stock', '<=', 20)->orderBy('stock')->orderBy('id')->limit(6)->get(['id', 'title', 'stock', 'thumbnail']),
            'updatedAt' => now()->toISOString(),
        ]]);
    }
}
