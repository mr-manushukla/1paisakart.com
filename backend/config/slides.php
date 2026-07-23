<?php

/*
 * Homepage hero slides.
 *
 * These are only the FALLBACK — once an admin saves from the dashboard the list
 * lives in the `settings` table under `hero_slides`. Wrap a fragment of a
 * heading in *asterisks* to highlight it.
 */

return [
    // Gradient presets an admin may pick. The literal Tailwind classes live in
    // frontend/src/lib/gradients.js so the build keeps them — keys must match.
    'gradients' => ['green', 'dark', 'amber', 'blue', 'rose'],

    'defaults' => [
        [
            'eyebrow' => 'The 1% draw',
            'heading' => 'Pay just *1%*. Win the whole thing.',
            'text' => 'Book any product with a 1% advance. Win the pool and it’s yours. If not, pay the balance to buy it — or keep the 1% as wallet credit.',
            'cta_label' => 'Explore 1% draws',
            'cta_to' => '/shop?mode=draw',
            'gradient' => 'green',
            'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80&auto=format&fit=crop',
        ],
        [
            'eyebrow' => 'Everyday value',
            'heading' => 'Shop top products, *buy now*.',
            'text' => 'Prefer certainty? Buy outright and put your wallet credit toward the price of each item.',
            'cta_label' => 'Shop all products',
            'cta_to' => '/shop',
            'gradient' => 'dark',
            'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&q=80&auto=format&fit=crop',
        ],
        [
            'eyebrow' => 'Transparent by design',
            'heading' => 'Every pool is *100% public*.',
            'text' => 'See exactly who has joined each draw, live. No hidden odds — one winner in 100, and a clear choice for the rest.',
            'cta_label' => 'See a live pool',
            'cta_to' => '/product/smart-watch-series-x',
            'gradient' => 'amber',
            'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80&auto=format&fit=crop',
        ],
    ],
];
