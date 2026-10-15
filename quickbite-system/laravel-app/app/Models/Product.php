<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Product extends Model { use HasFactory; protected $fillable=['category_id','name','slug','description','price','image_url','stock','is_available','is_featured']; protected $casts=['price'=>'decimal:2','is_available'=>'boolean','is_featured'=>'boolean','stock'=>'integer']; public function category(){return $this->belongsTo(Category::class);} }
