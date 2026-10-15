<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OrderController extends Controller {
 public function store(Request $request){$data=$request->validate(['customer_name'=>'required|string|max:120','email'=>'nullable|email|max:180','phone'=>'required|string|max:40','address'=>'required|string|max:500','order_type'=>'required|in:delivery,pickup','payment_method'=>'required|in:cod,gcash','notes'=>'nullable|string|max:1000','items'=>'required|array|min:1','items.*.product_id'=>'required|integer|exists:products,id','items.*.quantity'=>'required|integer|min:1|max:50']);
 $order=DB::transaction(function()use($data){$subtotal=0;$lines=[];foreach($data['items'] as $item){$p=Product::whereKey($item['product_id'])->lockForUpdate()->first();if(!$p||!$p->is_available||$p->stock<$item['quantity'])throw ValidationException::withMessages(['items'=>["Not enough stock for ".($p?->name??'an item')."."]]);$total=(float)$p->price*$item['quantity'];$subtotal+=$total;$lines[]=[$p,$item['quantity'],$total];}
 $fee=$data['order_type']==='delivery'?50:0;$order=Order::create(['order_number'=>'QB'.now()->format('ymd').strtoupper(bin2hex(random_bytes(3))),'customer_name'=>$data['customer_name'],'email'=>$data['email']??null,'phone'=>$data['phone'],'address'=>$data['address'],'order_type'=>$data['order_type'],'payment_method'=>$data['payment_method'],'payment_status'=>'pending','status'=>'pending','subtotal'=>$subtotal,'delivery_fee'=>$fee,'total'=>$subtotal+$fee,'notes'=>$data['notes']??null]);foreach($lines as [$p,$qty,$total]){$order->items()->create(['product_id'=>$p->id,'product_name'=>$p->name,'quantity'=>$qty,'unit_price'=>$p->price,'line_total'=>$total]);$p->decrement('stock',$qty);if($p->stock<1)$p->update(['is_available'=>false]);}return $order->load('items');});return response()->json(['message'=>'Order placed successfully','order'=>$order],201); }
 public function track(Request $request,string $orderNumber){$phone=$request->query('phone');if(!$phone)return response()->json(['message'=>'Enter the phone number used for the order.'],422);$order=Order::with('items')->where('order_number',$orderNumber)->where('phone',$phone)->first();if(!$order)return response()->json(['message'=>'Order not found. Check the order number and phone number.'],404);return $order;}
}
