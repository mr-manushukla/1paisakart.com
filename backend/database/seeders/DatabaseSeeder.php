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

        // [shop, category, name, price₹, stock, full_buy, unsplash-photo-id]
        // The 1% draw is global — every active product in a club band qualifies.
        $rows = [
            [$shop1, 'Electronics', 'Wireless Earbuds Pro', 1999, 50, true, '1590658268037-6bf12165a8df'],
            [$shop1, 'Electronics', 'Smart Watch Series X', 4999, 30, true, '1523275335684-37898b6baf30'],
            [$shop1, 'Electronics', 'Fast Power Bank 20000mAh', 1299, 80, true, '1609091839311-d5365f9ff1c5'],
            [$shop2, 'Fashion', 'Premium Cotton T-Shirt', 599, 200, true, '1521572163474-6864f9cf17ab'],
            [$shop2, 'Fashion', 'Road Runner Shoes', 2499, 40, true, '1542291026-7eec264c27ff'],
            [$shop2, 'Home & Kitchen', 'Steel Insulated Bottle 1L', 399, 150, true, '1602143407151-7111542de6e8'],
            [$shop1, 'Home & Kitchen', 'Non-stick Frying Pan', 899, 60, true, '1556910633-5099dc3971e8'],
            [$shop2, 'Grocery', 'Organic Forest Honey 500g', 349, 120, true, '1587049352846-4a222e784d38'],
        ];

        $products = collect($rows)->map(function ($r) use ($cats, $img) {
            [$shop, $cat, $name, $rupees, $stock, $fullBuy, $photo] = $r;

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
                'status' => 'active',
            ]);
        });

        // Gallery images (up to 5) + brand + product-information specs.
        $details = [
            'Wireless Earbuds Pro' => ['brand' => 'SonicWave', 'photos' => ['1590658268037-6bf12165a8df', '1505740420928-5e560c06d30e', '1611186871348-b1ce696e52c9', '1546435770-a3e426bf472b'],
                'specs' => ['Model' => 'SW-Buds Pro', 'Color' => 'Charcoal Black', 'Weight' => '48 g (with case)', 'Dimensions' => '6 × 4.5 × 3 cm', 'Connectivity' => 'Bluetooth 5.3', 'Battery' => '28 h with case', 'Care' => 'Wipe with a dry cloth; keep the case charged', 'Shipping' => 'Free delivery in 2–4 days · 7-day returns']],
            'Smart Watch Series X' => ['brand' => 'PulseTech', 'photos' => ['1523275335684-37898b6baf30', '1572569511254-d8f925fe2cbb', '1546435770-a3e426bf472b', '1600294037681-c80b4cb5b434'],
                'specs' => ['Model' => 'PT-X', 'Color' => 'Midnight', 'Size' => '44 mm case', 'Weight' => '52 g', 'Dimensions' => '4.4 × 3.8 × 1.1 cm', 'Display' => '1.9" AMOLED', 'Water resistance' => '5 ATM', 'Care' => 'Rinse after workouts; avoid hot water', 'Shipping' => 'Free delivery in 2–4 days · 7-day returns']],
            'Fast Power Bank 20000mAh' => ['brand' => 'VoltEdge', 'photos' => ['1609091839311-d5365f9ff1c5', '1600294037681-c80b4cb5b434', '1505740420928-5e560c06d30e'],
                'specs' => ['Model' => 'VE-20K', 'Color' => 'Graphite', 'Capacity' => '20000 mAh', 'Weight' => '340 g', 'Dimensions' => '15 × 7 × 2.5 cm', 'Ports' => '2× USB-A, 1× USB-C PD', 'Care' => 'Do not expose to moisture', 'Shipping' => 'Free delivery in 2–4 days']],
            'Premium Cotton T-Shirt' => ['brand' => 'UrbanThread', 'photos' => ['1521572163474-6864f9cf17ab', '1489987707025-afc232f7ea0f', '1520975954732-35dd22299614'],
                'specs' => ['Color' => 'Sky Blue', 'Size' => 'S / M / L / XL', 'Material' => '100% Combed Cotton', 'Fit' => 'Regular', 'Weight' => '180 gsm', 'Care' => 'Machine wash cold, tumble dry low', 'Shipping' => 'Free delivery in 3–5 days · 15-day returns']],
            'Road Runner Shoes' => ['brand' => 'StrideOne', 'photos' => ['1542291026-7eec264c27ff', '1556906781-9a412961c28c', '1595950653106-6c9ebd614d3a', '1600185365926-3a2ce3cdb9eb'],
                'specs' => ['Model' => 'Runner Flyknit', 'Color' => 'Crimson Red', 'Size' => 'UK 6–11', 'Material' => 'Flyknit upper, EVA sole', 'Weight' => '240 g', 'Care' => 'Spot clean; air dry', 'Shipping' => 'Free delivery in 3–5 days · 15-day returns']],
            'Steel Insulated Bottle 1L' => ['brand' => 'HydraKeep', 'photos' => ['1602143407151-7111542de6e8', '1523362628745-0c100150b504', '1594385208974-2e75f8d7bb48'],
                'specs' => ['Color' => 'Brushed Steel', 'Capacity' => '1 Litre', 'Material' => '18/8 Stainless Steel', 'Weight' => '360 g', 'Dimensions' => '28 × 7 cm', 'Insulation' => 'Hot 12 h / Cold 24 h', 'Care' => 'Hand wash only', 'Shipping' => 'Free delivery in 3–5 days']],
            'Non-stick Frying Pan' => ['brand' => 'ChefCraft', 'photos' => ['1556910633-5099dc3971e8', '1590794056226-79ef3a8147e1', '1594385208974-2e75f8d7bb48'],
                'specs' => ['Model' => 'CC-28', 'Color' => 'Black', 'Size' => '28 cm', 'Material' => 'Aluminium, PFOA-free coating', 'Weight' => '780 g', 'Care' => 'Hand wash; use soft utensils', 'Shipping' => 'Free delivery in 3–5 days']],
            'Organic Forest Honey 500g' => ['brand' => 'BeePure', 'photos' => ['1587049352846-4a222e784d38', '1558642891-54be180ea339', '1471943311424-646960669fbc', '1600271886742-f049cd451bba'],
                'specs' => ['Weight' => '500 g', 'Type' => 'Raw, unfiltered', 'Ingredients' => '100% wild forest honey', 'Shelf life' => '24 months', 'Care' => 'Store in a cool, dry place', 'Shipping' => 'Free delivery in 2–4 days']],
        ];

        $products->each(function ($p) use ($details, $img) {
            $d = $details[$p->name] ?? null;
            if ($d) {
                $p->update([
                    'brand' => $d['brand'],
                    'images' => array_map($img, $d['photos']),
                    // ordered [{label,value}] — MySQL JSON reorders object keys, arrays keep order.
                    'specs' => collect($d['specs'])->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all(),
                ]);
            }
        });

        // Demo customers; 42 of them join the flagship draw so the transparency
        // page shows a live, partially-filled pool (42/100).
        $customers = collect(range(1, 50))->map(
            fn ($i) => $this->user("Customer $i", "customer$i@1paisakart.test", 'customer')
        );

        // Smart Watch (₹4,999) and Road Runner Shoes (₹2,499) both fall in the
        // ₹1,001–₹5,000 club, so they share ONE pool — that's the club model.
        $watch = $products->firstWhere('name', 'Smart Watch Series X');
        $shoes = $products->firstWhere('name', 'Road Runner Shoes');
        $draw = app(DrawService::class);
        $customers->take(30)->each(fn ($c) => $draw->enter($watch, $c));
        $customers->slice(30, 12)->each(fn ($c) => $draw->enter($shoes, $c));

        // A couple of demo discounts + coupons so the UI has something to show.
        $products->firstWhere('name', 'Smart Watch Series X')?->update(['sale_price' => 3899 * 100]);   // ₹4,999 -> ₹3,899
        $products->firstWhere('name', 'Premium Cotton T-Shirt')?->update(['sale_price' => 449 * 100]);  // ₹599 -> ₹449

        \App\Models\Coupon::create(['shop_id' => $shop1->id, 'code' => 'RAVI10', 'type' => 'percent', 'value' => 10, 'max_discount' => 50000, 'per_user_limit' => 1]);
        \App\Models\Coupon::create(['shop_id' => $shop2->id, 'code' => 'MEENA100', 'type' => 'fixed', 'value' => 10000, 'min_order' => 50000, 'per_user_limit' => 1]);
        \App\Models\Coupon::create(['shop_id' => null, 'code' => 'WELCOME5', 'type' => 'percent', 'value' => 5, 'max_discount' => 20000, 'per_user_limit' => 1]);

        $this->seedReviews($products, $customers);

        $this->command?->info('Seeded: admin, 2 vendors, '.$products->count().' products, 50 customers, reviews, and one ₹1,001–₹5,000 club pool at 42/100 (mixed products).');
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
