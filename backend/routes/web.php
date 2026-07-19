<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// One-time DB setup for shell-less hosts. Protected by DEPLOY_TOKEN. Safe to hit
// more than once (seeds only when empty). Remove this route once the site is live.
Route::get('/__deploy', function () {
    abort_unless(request('token') && hash_equals((string) config('app.deploy_token'), (string) request('token')), 403);

    Artisan::call('migrate', ['--force' => true]);
    $seeded = false;
    if (User::count() === 0) {
        Artisan::call('db:seed', ['--force' => true]);
        $seeded = true;
    }

    return response()->json([
        'ok' => true,
        'seeded' => $seeded,
        'users' => User::count(),
        'products' => \App\Models\Product::count(),
    ]);
});

// Serve the built Vue SPA for everything that isn't an API/asset route.
Route::fallback(function () {
    $request = request();
    // A missing API route or static asset must 404 — don't hand back the SPA shell
    // (otherwise a deleted image would "200" with an HTML page).
    if ($request->is('api/*', 'uploads/*', 'assets/*', 'storage/*')
        || pathinfo($request->path(), PATHINFO_EXTENSION) !== '') {
        abort(404);
    }
    $spa = public_path('spa.html');
    abort_unless(file_exists($spa), 404, 'SPA build not found.');

    return response(file_get_contents($spa))->header('Content-Type', 'text/html');
});
