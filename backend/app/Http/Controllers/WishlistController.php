<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /** The signed-in customer's saved products. */
    public function index(Request $request)
    {
        $ids = $request->user()->wishlists()->pluck('product_id');

        return ProductResource::collection(
            Product::whereIn('id', $ids)->where('status', 'active')
                ->withAvg('reviews', 'rating')->withCount('reviews')
                ->with(['category'])
                ->get()
        );
    }

    /** Toggle a product in/out of the wishlist. Returns the new state. */
    public function toggle(Request $request, Product $product): array
    {
        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)->first();

        if ($existing) {
            $existing->delete();

            return ['wished' => false];
        }

        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        return ['wished' => true];
    }
}
