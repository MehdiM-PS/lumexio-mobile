<?php

use App\Models\LocalState;
use App\NativeComponents\Screens\SelectShop;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('lists the shops the user can access', function () {
    Http::fake(['*/shops' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
    ]], 200)]);

    Native::test(SelectShop::class)
        ->assertSee('Ma Boutique')
        ->assertSee('Autre Boutique');
});

it('auto-selects and navigates when there is exactly one shop', function () {
    Http::fake(['*/shops' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
    ]], 200)]);

    Native::test(SelectShop::class)->assertReplacedWith('/dashboard');

    expect(LocalState::current()->fresh()->shop_id)->toBe('shop-1');
});

it('navigates to the dashboard when the user taps a shop', function () {
    Http::fake(['*/shops' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
    ]], 200)]);

    Native::test(SelectShop::class)
        ->call('select', 'shop-2')
        ->assertReplacedWith('/dashboard');

    expect(LocalState::current()->fresh()->shop_id)->toBe('shop-2');
});
