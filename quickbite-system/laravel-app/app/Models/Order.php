<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model { protected $fillable=['order_number','customer_name','email','phone','address','order_type','payment_method','payment_status','status','subtotal','delivery_fee','total','notes']; protected $casts=['subtotal'=>'decimal:2','delivery_fee'=>'decimal:2','total'=>'decimal:2']; public function items(){return $this->hasMany(OrderItem::class);} }
