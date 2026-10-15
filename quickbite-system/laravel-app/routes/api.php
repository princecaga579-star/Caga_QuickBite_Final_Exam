<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminController;

Route::get('/health', fn () => response()->json(['ok' => true, 'app' => 'QuickBite']));
Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);
Route::post('/orders', [OrderController::class, 'store']);
Route::post('/admin/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/logout', [AuthController::class, 'logout']);
    Route::get('/admin/me', [AuthController::class, 'me']);
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::apiResource('/categories', AdminController::class)->only(['store','update','destroy']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::put('/products/{product}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{product}', [AdminController::class, 'deleteProduct']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::patch('/orders/{order}/status', [AdminController::class, 'updateOrderStatus']);
        Route::patch('/products/{product}/stock', [AdminController::class, 'updateStock']);
        Route::get('/reports/sales', [AdminController::class, 'salesReport']);
    });
});
