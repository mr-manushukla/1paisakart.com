<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** The signed-in customer's own orders (100% buys + draw wins). */
    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with('items.product')->latest('id')->paginate(15)
        );
    }
}
