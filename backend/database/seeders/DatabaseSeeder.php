<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Services\DrawService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('platform_fee_pct', config('draw.default_platform_fee_pct', 8));

        $this->user('Admin', 'admin@1paisakart.test', 'admin');

        $v1 = $this->user('Ravi Traders', 'vendor1@1paisakart.test', 'vendor');
        $v2 = $this->user('Meena Mart', 'vendor2@1paisakart.test', 'vendor');
        $shop1 = Shop::create(['user_id' => $v1->id, 'name' => 'Ravi Traders', 'slug' => 'ravi-traders']);
        $shop2 = Shop::create(['user_id' => $v2->id, 'name' => 'Meena Mart', 'slug' => 'meena-mart']);

        $cats = collect(['Electronics', 'Fashion', 'Home & Kitchen', 'Grocery'])
            ->mapWithKeys(fn ($n) => [$n => Category::create(['name' => $n, 'slug' => Str::slug($n)])]);

        // [shop, category, name, price₹, stock, full_buy, draw]
        $rows = [
            [$shop1, 'Electronics', 'Wireless Earbuds Pro', 1999, 50, true, true],
            [$shop1, 'Electronics', 'Smart Watch Series X', 4999, 30, true, true],
            [$shop1, 'Electronics', 'Fast Power Bank 20000mAh', 1299, 80, true, false],
            [$shop2, 'Fashion', 'Premium Cotton T-Shirt', 599, 200, true, false],
            [$shop2, 'Fashion', 'Road Runner Shoes', 2499, 40, true, true],
            [$shop2, 'Home & Kitchen', 'Steel Insulated Bottle 1L', 399, 150, true, false],
            [$shop1, 'Home & Kitchen', 'Non-stick Frying Pan', 899, 60, true, false],
            [$shop2, 'Grocery', 'Organic Forest Honey 500g', 349, 120, true, false],
        ];

        $products = collect($rows)->map(function ($r) use ($cats) {
            [$shop, $cat, $name, $rupees, $stock, $fullBuy, $draw] = $r;

            return Product::create([
                'shop_id' => $shop->id,
                'category_id' => $cats[$cat]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "$name — quality you can trust, at 1paisakart prices.",
                'listed_price' => $rupees * 100, // paise
                'stock' => $stock,
                'allow_full_buy' => $fullBuy,
                'allow_draw' => $draw,
                'status' => 'active',
            ]);
        });

        // Demo customers; 42 of them join the flagship draw so the transparency
        // page shows a live, partially-filled pool (42/100).
        $customers = collect(range(1, 50))->map(
            fn ($i) => $this->user("Customer $i", "customer$i@1paisakart.test", 'customer')
        );

        $flagship = $products->firstWhere('name', 'Smart Watch Series X');
        $draw = app(DrawService::class);
        $customers->take(42)->each(fn ($c) => $draw->enter($flagship, $c));

        $this->command?->info('Seeded: admin, 2 vendors, '.$products->count().' products, 50 customers, 1 open draw (42/100).');
    }

    private function user(string $name, string $email, string $role): User
    {
        $u = new User(['name' => $name, 'email' => $email, 'password' => 'password']);
        $u->role = $role; // guarded column, set directly
        $u->save();

        return $u;
    }
}
