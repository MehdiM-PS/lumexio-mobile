<?php

use App\NativeComponents\Screens\Recommendations;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeRecommendationsScreenEndpoints(): void
{
    Http::fake([
        '*/recommendations/actioned*' => Http::response(['recommendations' => []], 200),
        '*/recommendations/rejected*' => Http::response(['recommendations' => []], 200),
        '*/recommendations*' => Http::response([
            'recommendations' => [],
            'stats' => ['total' => 0, 'high_priority' => 0],
        ], 200),
        // Recommendations now mixes in HasHeaderChrome and loads the
        // current user via loadCurrentUser() on mount (Task 9) — left
        // unfaked, /auth/me falls through unmatched and throws.
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']], 200),
    ]);
}

it('shows the tab bar with a Recommendations tab', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertHasTab('Accueil')
        ->assertHasTab('Reco')
        ->assertTabActive('Reco');
});

it('shows the inner tab-row with three tabs', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Ouvertes')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Traitées')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Rejetées');
});

it('shows the open tab by default and switches to actioned and rejected', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertSee('Aucune recommandation pour le moment.')
        ->set('activeTab', 1)
        ->assertSee('Aucune recommandation traitée.')
        ->set('activeTab', 2)
        ->assertSee('Aucune recommandation rejetée.');
});

it('opens the shop switcher sheet from the shared header trait', function () {
    fakeRecommendationsScreenEndpoints();
    // A non-empty fixture (not '*/shops*' => []) makes this fake load-bearing:
    // the tree assertions below prove the sheet partial actually rendered the
    // fetched row, not just that shopSheetOpen flipped to true.
    Http::fake(['*/shops*' => Http::response(['shops' => [
        ['id' => 'shop-1', 'name' => 'Boutique Principale', 'platform' => 'prestashop'],
    ]])]);

    $screen = Native::test(Recommendations::class);
    $screen->call('openShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeTrue();

    $tree = $screen->tree();
    expect(findNodeByRef($tree, 'shop-switcher-sheet')['props']['visible'] ?? null)->toBeTruthy();
    expect(findNodeByRef($tree, 'shop-switcher-row-shop-1'))->not->toBeNull();
});
