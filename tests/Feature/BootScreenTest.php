<?php

use App\Models\LocalState;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('goes straight to login when no token is stored', function () {
    Native::visit('/')->assertReplacedWith('/login');
});

it('goes to login when the stored token is rejected by the API', function () {
    LocalState::current()->update(['token' => 'stale-token']);
    Http::fake(['*/auth/me' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    Native::visit('/')->assertReplacedWith('/login');
});

it('goes to shop selection when authenticated but no shop is selected', function () {
    LocalState::current()->update(['token' => 'valid-token']);
    Http::fake(['*/auth/me' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => true],
    ], 200)]);

    Native::visit('/')->assertReplacedWith('/shops/select');
});

it('goes straight to the dashboard when authenticated with a shop already selected', function () {
    LocalState::current()->update(['token' => 'valid-token', 'shop_id' => 'shop-1']);
    Http::fake(['*/auth/me*' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => true],
    ], 200)]);

    Native::visit('/')->assertReplacedWith('/dashboard');
});
