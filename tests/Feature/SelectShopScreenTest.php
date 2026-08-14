<?php

use App\Models\LocalState;
use App\NativeComponents\Screens\SelectShop;
use Illuminate\Http\Client\ConnectionException;
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

it('auto-selects and navigates when the stored shop_id is still among the fetched shops', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    Http::fake(['*/shops*' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
    ]], 200)]);

    Native::test(SelectShop::class)->assertReplacedWith('/dashboard');

    expect(LocalState::current()->fresh()->shop_id)->toBe('shop-1');
});

it('does not trust a stored shop_id that is no longer in the fetched shops list', function () {
    LocalState::current()->update(['shop_id' => 'stale-shop']);
    Http::fake(['*/shops*' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
    ]], 200)]);

    Native::test(SelectShop::class)
        ->assertNoNavigation()
        ->assertSee('Ma Boutique')
        ->assertSee('Autre Boutique');

    expect(LocalState::current()->fresh()->shop_id)->toBe('stale-shop');
});

it('shows the API error message when fetching shops fails', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    Native::test(SelectShop::class)
        ->assertSee('Connexion indisponible. Vérifie ta connexion et réessaie.');
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
