<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Public: all reviews for a product, newest first, plus a summary. */
    public function index(Request $request, Product $product)
    {
        $agg = $product->reviews()->selectRaw('count(*) c, avg(rating) a')->first();

        return ReviewResource::collection(
            $product->reviews()->with('user:id,name')->latest('id')->paginate(10)
        )->additional(['summary' => [
            'count' => (int) $agg->c,
            'average' => $agg->a ? round((float) $agg->a, 1) : null,
            'can_review' => $request->user() ? $product->purchasedBy($request->user()) : false,
        ]]);
    }

    /** Verified buyers only. One review per buyer (upsert). */
    public function store(Request $request, Product $product): ReviewResource
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $product->purchasedBy($request->user())) {
            throw new BusinessException('Only buyers of this product can review it.');
        }

        $review = Review::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data,
        );

        return new ReviewResource($review->load('user:id,name'));
    }
}
