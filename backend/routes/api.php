<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SlideController;
use App\Http\Controllers\WinnerController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Vendor\CouponController as VendorCouponController;
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
    ->orderBy('name')->get(['id', 'name', 'slug', 'icon']));
Route::get('/slides', [SlideController::class, 'index']); // homepage hero, admin-editable
Route::get('/winners', [WinnerController::class, 'index']); // public winner board
Route::get('/products', [ProductController::class, 'index']);
Route::get('/price-range', [ProductController::class, 'priceRange']); // bounds for the price filter
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
        Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
        Route::post('/payments/order', [PaymentController::class, 'createOrder']);
        Route::post('/payments/verify', [PaymentController::class, 'verify']);
        Route::get('/my-draws', [DrawController::class, 'myDraws']);
        Route::post('/draw-entries/{entry}/purchase', [DrawController::class, 'purchase']); // Option A
        Route::post('/draw-entries/{entry}/credit', [DrawController::class, 'credit']);     // Option B
        Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/products/{product:slug}/wishlist', [WishlistController::class, 'toggle']);
    });

    // Vendor
    Route::middleware('role:vendor')->prefix('vendor')->group(function () {
        Route::apiResource('products', VendorProductController::class)->except(['show']);
        Route::post('/products/{product}/images', [VendorProductController::class, 'uploadImages']);
        Route::delete('/products/{product}/images', [VendorProductController::class, 'deleteImage']);
        Route::post('/products/{product}/images/primary', [VendorProductController::class, 'setPrimaryImage']);
        Route::get('/coupons', [VendorCouponController::class, 'index']);
        Route::post('/coupons', [VendorCouponController::class, 'store']);
        Route::put('/coupons/{coupon}', [VendorCouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [VendorCouponController::class, 'destroy']);
        Route::get('/orders', [VendorSalesController::class, 'orders']);
        Route::get('/batches', [VendorSalesController::class, 'batches']); // read-only: pools are shared
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('categories', AdminCategoryController::class)->except(['show']);
        Route::get('/vendors', [AdminVendorController::class, 'index']);
        Route::post('/vendors', [AdminVendorController::class, 'store']);
        Route::get('/coupons', [AdminCouponController::class, 'index']);
        Route::post('/coupons', [AdminCouponController::class, 'store']);
        Route::post('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle']);
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);
        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{user}/draws', [AdminCustomerController::class, 'draws']);
        Route::get('/batches', [PlatformController::class, 'batches']);
        Route::post('/batches/{batch}/cancel', [PlatformController::class, 'cancelBatch']);
        Route::get('/settings', [PlatformController::class, 'settings']);
        Route::put('/settings', [PlatformController::class, 'updateSettings']);
        Route::put('/slides', [SlideController::class, 'update']);
        Route::post('/slides/image', [SlideController::class, 'uploadImage']);
    });
});
