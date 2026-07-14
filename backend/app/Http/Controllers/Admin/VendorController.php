<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function index()
    {
        return User::where('role', 'vendor')
            ->with('shop')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'email' => $v->email,
                'shop' => $v->shop?->name,
                'shop_slug' => $v->shop?->slug,
                'products' => $v->shop?->products()->count() ?? 0,
            ]);
    }

    /** Admin creates a vendor account + their shop in one step. */
    public function store(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'shop_name' => ['required', 'string', 'max:160'],
        ]);

        $vendor = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $vendor->role = 'vendor';
        $vendor->save();

        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => $data['shop_name'],
            'slug' => Str::slug($data['shop_name']).'-'.Str::lower(Str::random(3)),
        ]);

        return ['message' => 'Vendor created.', 'vendor_id' => $vendor->id, 'shop_id' => $shop->id];
    }
}
