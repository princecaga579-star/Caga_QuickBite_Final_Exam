<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
class CatalogController extends Controller {
 public function categories(){return Category::where('is_active',true)->orderBy('name')->get();}
 public function products(Request $request){$q=Product::with('category')->where('is_available',true)->where('stock','>',0); if($request->filled('category'))$q->where('category_id',$request->integer('category')); if($request->filled('search'))$q->where('name','like','%'.$request->string('search').'%'); if($request->boolean('featured'))$q->where('is_featured',true); return $q->orderByDesc('is_featured')->orderBy('name')->get();}
}
