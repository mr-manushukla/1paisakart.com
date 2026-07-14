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

        $img = fn ($id) => "https://images.unsplash.com/photo-$id?w=600&q=80&auto=format&fit=crop";

        // [shop, category, name, price₹, stock, full_buy, draw, unsplash-photo-id]
        $rows = [
            [$shop1, 'Electronics', 'Wireless Earbuds Pro', 1999, 50, true, true, '1590658268037-6bf12165a8df'],
            [$shop1, 'Electronics', 'Smart Watch Series X', 4999, 30, true, true, '1523275335684-37898b6baf30'],
            [$shop1, 'Electronics', 'Fast Power Bank 20000mAh', 1299, 80, true, false, '1609091839311-d5365f9ff1c5'],
            [$shop2, 'Fashion', 'Premium Cotton T-Shirt', 599, 200, true, false, '1521572163474-6864f9cf17ab'],
            [$shop2, 'Fashion', 'Road Runner Shoes', 2499, 40, true, true, '1542291026-7eec264c27ff'],
            [$shop2, 'Home & Kitchen', 'Steel Insulated Bottle 1L', 399, 150, true, false, '1602143407151-7111542de6e8'],
            [$shop1, 'Home & Kitchen', 'Non-stick Frying Pan', 899, 60, true, false, '1556910633-5099dc3971e8'],
            [$shop2, 'Grocery', 'Organic Forest Honey 500g', 349, 120, true, false, '1587049352846-4a222e784d38'],
        ];

        $products = collect($rows)->map(function ($r) use ($cats, $img) {
            [$shop, $cat, $name, $rupees, $stock, $fullBuy, $draw, $photo] = $r;

            return Product::create([
                'shop_id' => $shop->id,
                'category_id' => $cats[$cat]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "$name — quality you can trust, at 1paisakart prices.",
                'image' => $img($photo),
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

        $this->seedReviews($products, $customers);

        $this->command?->info('Seeded: admin, 2 vendors, '.$products->count().' products, 50 customers, 1 open draw (42/100), reviews.');
    }

    /** A handful of realistic reviews per product (mixed 3–5★ reads more trustworthy than all-5★). */
    private function seedReviews($products, $customers): void
    {
        $texts = [
            5 => ['Absolutely love it — exceeded expectations!', 'Top quality and fast delivery. Highly recommend.', 'Best purchase this year. Worth every rupee.'],
            4 => ['Great value for the price, happy with it.', 'Solid product, does exactly what it says.', 'Good quality overall, would buy again.'],
            3 => ['Decent — does the job, nothing fancy.', 'Okay for the price; packaging could be better.'],
        ];
        $ratings = [5, 4, 5, 3, 4, 5];

        foreach ($products->values() as $pi => $product) {
            $reviewers = $customers->slice($pi * 4, 5)->values();
            foreach ($reviewers as $ri => $cust) {
                $r = $ratings[$ri % count($ratings)];
                \App\Models\Review::create([
                    'product_id' => $product->id,
                    'user_id' => $cust->id,
                    'rating' => $r,
                    'body' => $texts[$r][$ri % count($texts[$r])],
                ]);
            }
        }
    }

    private function user(string $name, string $email, string $role): User
    {
        $u = new User(['name' => $name, 'email' => $email, 'password' => 'password']);
        $u->role = $role; // guarded column, set directly
        $u->save();

        return $u;
    }
}
