<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Vendor\SalesController as VendorSalesController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// ---- Auth (Sanctum SPA, cookie session) ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// ---- Public catalog + draw transparency ----
Route::get('/categories', fn () => \App\Models\Category::query()
    ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
    ->orderBy('name')->get(['id', 'name', 'slug']));
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
Route::get('/products/{product:slug}/batch', [ProductController::class, 'batch']); // who's in the pool
Route::get('/products/{product:slug}/related', [ProductController::class, 'related']); // cross-sell
Route::get('/products/{product:slug}/reviews', [ReviewController::class, 'index']);

// ---- Authenticated ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Customer
    Route::middleware('role:customer')->group(function () {
        Route::post('/products/{product:slug}/enter-draw', [DrawController::class, 'enter']);
        Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/products/{product:slug}/wishlist', [WishlistController::class, 'toggle']);
    });

    // Vendor
    Route::middleware('role:vendor')->prefix('vendor')->group(function () {
        Route::apiResource('products', VendorProductController::class)->except(['show']);
        Route::get('/orders', [VendorSalesController::class, 'orders']);
        Route::get('/batches', [VendorSalesController::class, 'batches']);
        Route::post('/batches/{batch}/cancel', [VendorSalesController::class, 'cancelBatch']);
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('categories', AdminCategoryController::class)->except(['show']);
        Route::get('/vendors', [AdminVendorController::class, 'index']);
        Route::post('/vendors', [AdminVendorController::class, 'store']);
        Route::get('/batches', [PlatformController::class, 'batches']);
        Route::post('/batches/{batch}/cancel', [PlatformController::class, 'cancelBatch']);
        Route::get('/settings', [PlatformController::class, 'settings']);
        Route::put('/settings', [PlatformController::class, 'updateSettings']);
    });
});
