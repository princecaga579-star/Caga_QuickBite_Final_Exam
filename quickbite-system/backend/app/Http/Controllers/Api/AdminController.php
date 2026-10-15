<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Ensure the authenticated user is an admin.
     */
    private function admin(Request $request): void
    {
        abort_unless(
            $request->user()?->is_admin,
            403,
            'Admin access required.'
        );
    }

    /**
     * Admin dashboard statistics and data.
     */
    public function dashboard(Request $request)
    {
        $this->admin($request);

        return response()->json([
            'stats' => [
                'orders' => Order::count(),

                'pending' => Order::where(
                    'status',
                    'pending'
                )->count(),

                'products' => Product::count(),

                'low_stock' => Product::where(
                    'stock',
                    '<=',
                    5
                )->count(),

                'revenue' => (float) Order::whereIn(
                    'status',
                    [
                        'confirmed',
                        'preparing',
                        'ready',
                        'completed',
                    ]
                )->sum('total'),
            ],

            'recent_orders' => Order::with('items')
                ->latest()
                ->limit(8)
                ->get(),

            'products' => Product::with('category')
                ->orderBy('name')
                ->get(),

            'categories' => Category::orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Create a category.
     */
    public function store(Request $request)
    {
        $this->admin($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Update a category.
     */
    public function update(
        Request $request,
        Category $category
    ) {
        $this->admin($request);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json($category->fresh());
    }

    /**
     * Delete a category if it has no products.
     */
    public function destroy(
        Request $request,
        Category $category
    ) {
        $this->admin($request);

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Move or delete this category’s products first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    /**
     * Validate product data.
     */
    private function productData(Request $request): array
    {
        return $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'image_url' => 'nullable|url|max:1000',
            'stock' => 'required|integer|min:0',
            'is_available' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
        ]);
    }

    /**
     * Create a product.
     */
    public function storeProduct(Request $request)
    {
        $this->admin($request);

        $data = $this->productData($request);

        $data['slug'] = Str::slug($data['name'])
            . '-'
            . Str::lower(Str::random(5));

        if ($data['stock'] === 0) {
            $data['is_available'] = false;
        }

        $product = Product::create($data);

        return response()->json(
            $product->load('category'),
            201
        );
    }

    /**
     * Update a product.
     */
    public function updateProduct(
        Request $request,
        Product $product
    ) {
        $this->admin($request);

        $data = $this->productData($request);

        if ($data['stock'] === 0) {
            $data['is_available'] = false;
        }

        $product->update($data);

        return response()->json(
            $product->fresh()->load('category')
        );
    }

    /**
     * Delete a product.
     *
     * Prevent deletion when order items reference the product.
     */
    public function deleteProduct(
        Request $request,
        Product $product
    ) {
        $this->admin($request);

        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'This product has order history and cannot be deleted.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * Update product stock.
     */
    public function updateStock(
        Request $request,
        Product $product
    ) {
        $this->admin($request);

        $data = $request->validate([
            'stock' => 'required|integer|min:0',
        ]);

        $product->update([
            'stock' => $data['stock'],
            'is_available' => $data['stock'] > 0,
        ]);

        return response()->json($product->fresh());
    }

    /**
     * List orders with pagination.
     */
    public function orders(Request $request)
    {
        $this->admin($request);

        return response()->json(
            Order::with('items')
                ->latest()
                ->paginate(20)
        );
    }

    /**
     * Update an order status.
     */
    public function updateOrderStatus(
        Request $request,
        Order $order
    ) {
        $this->admin($request);

        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,completed,cancelled',
        ]);

        $order->update([
            'status' => $data['status'],
        ]);

        return response()->json(
            $order->fresh()->load('items')
        );
    }

    /**
     * Sales report.
     */
    public function salesReport(Request $request)
    {
        $this->admin($request);

        $days = max(
            1,
            min(90, (int) $request->query('days', 7))
        );

        $from = now()
            ->subDays($days - 1)
            ->startOfDay();

        $revenueStatuses = [
            'confirmed',
            'preparing',
            'ready',
            'completed',
        ];

        $daily = Order::query()
            ->selectRaw(
                'DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue'
            )
            ->whereIn('status', $revenueStatuses)
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $totalRevenue = Order::whereIn(
            'status',
            $revenueStatuses
        )
            ->where('created_at', '>=', $from)
            ->sum('total');

        $totalOrders = Order::where(
            'created_at',
            '>=',
            $from
        )->count();

        $bestSellers = OrderItem::query()
            ->selectRaw(
                'product_name, SUM(quantity) as quantity_sold, SUM(line_total) as revenue'
            )
            ->whereHas('order', function ($query) use (
                $revenueStatuses,
                $from
            ) {
                $query->whereIn(
                    'status',
                    $revenueStatuses
                )->where(
                    'created_at',
                    '>=',
                    $from
                );
            })
            ->groupBy('product_name')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get();

        return response()->json([
            'days' => $days,
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'daily' => $daily,
            'best_sellers' => $bestSellers,
        ]);
    }
}
