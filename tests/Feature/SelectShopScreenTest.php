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

it('shows an explanatory message and a retry action when the account has zero accessible shops', function () {
    Http::fake(['*/shops' => Http::response(['shops' => []], 200)]);

    Native::test(SelectShop::class)
        ->assertNoNavigation()
        ->assertSee('Aucune boutique accessible avec ce compte.');
});

it('retries the shops fetch when the retry action is pressed after a zero-shops response', function () {
    Http::fake(['*/shops' => Http::sequence()
        ->push(['shops' => []], 200)
        ->push(['shops' => [
            ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ]], 200),
    ]);

    $test = Native::test(SelectShop::class)
        ->assertNoNavigation()
        ->assertSee('Aucune boutique accessible avec ce compte.');

    $test->call('retry')->assertReplacedWith('/dashboard');

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

// Bug 3 fix: pull-to-refresh on the shop list, wired to the same private
// fetchShops() the existing retry() button already reuses.
it('wraps the shop list in a refreshable element', function () {
    Http::fake(['*/shops' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
        ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
    ]], 200)]);

    // Refreshable::resolveProps() registers the bound method as
    // props.on_refresh (a numeric callback id) — the strongest check the
    // wire-tree format supports for "is a handler actually bound" short
    // of decoding the CallbackRegistry.
    Native::test(SelectShop::class)
        ->assertElement('refreshable', fn (array $n): bool => isset($n['props']['on_refresh']));
});

it('refetches shops on pull-to-refresh', function () {
    Http::fake(['*/shops' => Http::sequence()
        ->push(['shops' => [
            ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
            ['id' => 'shop-2', 'name' => 'Autre Boutique', 'domain' => 'autre.com'],
        ]], 200)
        ->push(['shops' => [
            ['id' => 'shop-1', 'name' => 'Ma Boutique', 'domain' => 'ma-boutique.com'],
            ['id' => 'shop-3', 'name' => 'Nouvelle Boutique', 'domain' => 'nouvelle.com'],
        ]], 200),
    ]);

    $screen = Native::test(SelectShop::class)
        ->assertSee('Autre Boutique');

    // The refreshable's @refresh handler calls this same method — this
    // proves pull-to-refresh actually re-fetches rather than reusing the
    // shops fetched on mount.
    $screen->call('refresh')
        ->assertSee('Nouvelle Boutique')
        ->assertDontSee('Autre Boutique');
});
