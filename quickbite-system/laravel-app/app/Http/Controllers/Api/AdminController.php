<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AdminController extends Controller {
 private function admin(Request $r){abort_unless($r->user()?->is_admin,403,'Admin access required.');}
 public function dashboard(Request $r){$this->admin($r);return ['stats'=>['orders'=>Order::count(),'pending'=>Order::where('status','pending')->count(),'products'=>Product::count(),'low_stock'=>Product::where('stock','<=',5)->count(),'revenue'=>(float)Order::whereIn('status',['confirmed','preparing','ready','completed'])->sum('total')],'recent_orders'=>Order::with('items')->latest()->limit(8)->get(),'products'=>Product::with('category')->orderBy('name')->get(),'categories'=>Category::orderBy('name')->get()];}
 public function store(Request $r){$this->admin($r);$d=$r->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:500','is_active'=>'sometimes|boolean']);return Category::create($d+['slug'=>Str::slug($d['name'])]);}
 public function update(Request $r,Category $category){$this->admin($r);$d=$r->validate(['name'=>'sometimes|required|string|max:100','description'=>'nullable|string|max:500','is_active'=>'sometimes|boolean']);if(isset($d['name']))$d['slug']=Str::slug($d['name']);$category->update($d);return $category;}
 public function destroy(Request $r,Category $category){$this->admin($r);if($category->products()->exists())return response()->json(['message'=>'Move or delete this category’s products first.'],422);$category->delete();return response()->json(['message'=>'Category deleted']);}
 private function productData(Request $r){return $r->validate(['category_id'=>'required|exists:categories,id','name'=>'required|string|max:150','description'=>'nullable|string|max:1000','price'=>'required|numeric|min:0.01','image_url'=>'nullable|url|max:1000','stock'=>'required|integer|min:0','is_available'=>'sometimes|boolean','is_featured'=>'sometimes|boolean']);}
 public function storeProduct(Request $r){$this->admin($r);$d=$this->productData($r);$d['slug']=Str::slug($d['name']).'-'.Str::lower(Str::random(5));return Product::create($d)->load('category');}
 public function updateProduct(Request $r,Product $product){$this->admin($r);$d=$this->productData($r);$product->update($d);return $product->load('category');}
 public function deleteProduct(Request $r,Product $product){$this->admin($r);$product->delete();return response()->json(['message'=>'Product deleted']);}
 public function updateStock(Request $r,Product $product){$this->admin($r);$d=$r->validate(['stock'=>'required|integer|min:0']);$product->update(['stock'=>$d['stock'],'is_available'=>$d['stock']>0]);return $product;}
 public function orders(Request $r){$this->admin($r);return Order::with('items')->latest()->paginate(20);}
 public function updateOrderStatus(Request $r,Order $order){$this->admin($r);$d=$r->validate(['status'=>'required|in:pending,confirmed,preparing,ready,completed,cancelled']);$order->update(['status'=>$d['status']]);return $order->load('items');}
 public function salesReport(Request $r){$this->admin($r);$days=max(1,min(90,(int)$r->query('days',7)));$from=now()->subDays($days-1)->startOfDay();$daily=Order::selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')->whereIn('status',['confirmed','preparing','ready','completed'])->where('created_at','>=',$from)->groupBy('day')->orderBy('day')->get();return ['days'=>$days,'total_revenue'=>(float)Order::whereIn('status',['confirmed','preparing','ready','completed'])->where('created_at','>=',$from)->sum('total'),'total_orders'=>Order::where('created_at','>=',$from)->count(),'daily'=>$daily,'best_sellers'=>\App\Models\OrderItem::selectRaw('product_name, SUM(quantity) as quantity_sold, SUM(line_total) as revenue')->whereHas('order',fn($q)=>$q->whereIn('status',['confirmed','preparing','ready','completed'])->where('created_at','>=',$from))->groupBy('product_name')->orderByDesc('quantity_sold')->limit(5)->get()];}
}
