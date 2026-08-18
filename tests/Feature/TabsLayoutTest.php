<?php

use App\Models\LocalState;
use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Screens\Dashboard;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Testing\Native;

/**
 * Mirrors DashboardScreenTest's fixture shapes (Dashboard::refresh() hits
 * all of these) rather than reusing that file's global helper, so this
 * test file's fixtures don't silently depend on another test file having
 * been loaded first.
 */
function fakeTabsLayoutEndpoints(): void
{
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/dashboard/metrics*' => Http::response(['metrics' => [
            'revenue_today' => 0, 'orders_today' => 0, 'revenue_period' => 0, 'orders_period' => 0,
            'avg_order_value' => 0, 'products_count' => 0, 'low_stock_count' => 0, 'customers_count' => 0,
        ]], 200),
        '*/dashboard/widgets*' => Http::response(['widgets' => null], 200),
        '*/forecasts*' => Http::response(['historical' => [], 'forecasts' => [], 'summary' => []], 200),
        '*/stock-depletion*' => Http::response(['stats' => [], 'products' => []], 200),
        '*/segments/stats*' => Http::response(['segments' => []], 200),
        '*/alerts*' => Http::response(['alerts' => []], 200),
        '*/shops*' => Http::response(['shops' => []], 200),
    ]);
}

it('declares exactly 5 tabs in order with the correct routes and labels', function () {
    fakeTabsLayoutEndpoints();
    $screen = Native::test(Dashboard::class);

    $tabBar = (new TabsLayout)->tabBar($screen->instance());

    // Tab has no public getLabel()/getRoute() — only getUrl() and the
    // fluent setters. tabElements() (public, ordered) gives ready-to-read
    // bottom_nav_item elements; getResolvedProps() with a throwaway
    // CallbackRegistry surfaces the 'label' prop the same way the real
    // render pipeline would.
    $items = $tabBar->tabElements();

    expect($items)->toHaveCount(5);

    $expected = [
        ['label' => 'Accueil', 'url' => '/dashboard'],
        ['label' => 'Prévisions', 'url' => '/forecasts'],
        ['label' => 'Ventes', 'url' => '/sales'],
        ['label' => 'Reco', 'url' => '/recommendations'],
        ['label' => 'Alertes', 'url' => '/alerts'],
    ];

    foreach ($expected as $i => $tab) {
        $props = $items[$i]->getResolvedProps(new CallbackRegistry);

        expect($items[$i]->getUrl())->toBe($tab['url']);
        expect($props['label'] ?? null)->toBe($tab['label']);
    }
});

it('renders the shared header with the cached shop name and alert badge from LocalState', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['active_shop_name' => 'Boutique Principale', 'unread_alert_count' => 3]);

    // Native::test() only applies native chrome (navBar/tabBar) when a
    // layout is passed explicitly — unlike Native::visit(), which resolves
    // it from the route registration. Without this, the header partial
    // never renders and both header assertions below pass vacuously for
    // the wrong reason.
    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);
    $tree = $screen->tree();

    $screen->assertSee('Boutique Principale');

    $badge = findNodeByRef($tree, 'header-alert-badge');
    expect($badge)->not->toBeNull();
    expect($badge['props']['label'] ?? null)->toBe('3');
});

it('renders no alert badge when unread_alert_count is zero', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['unread_alert_count' => 0]);

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);
    $tree = $screen->tree();

    // Anchor: the header itself DID render (else this assertion would
    // pass vacuously for the wrong reason — an unwired navBar()).
    expect(findNodeByRef($tree, 'header-alert-button'))->not->toBeNull();
    expect(findNodeByRef($tree, 'header-alert-badge'))->toBeNull();
});

it('renders the shop-pill logo image', function () {
    fakeTabsLayoutEndpoints();

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    expect(findNodeByRef($screen->tree(), 'header-shop-logo'))->not->toBeNull();
});

it('renders the shop-pill chevron with a resolved color, proving class-based styling reaches native:icon', function () {
    // native:icon is otherwise unused anywhere else in this codebase with a
    // `class`-derived color, so this isn't a given — it must actually reach
    // Icon::resolveProps()'s `color` prop, not just silently drop.
    fakeTabsLayoutEndpoints();

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    $chevron = findNodeByRef($screen->tree(), 'header-shop-chevron');
    expect($chevron)->not->toBeNull();
    expect($chevron['props']['color'] ?? null)->toBe(theme('on-surface-variant'));
});

it('positions the alert badge as an overlapping corner badge on the bell button, not an inline pill', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['unread_alert_count' => 2]);

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    $badge = findNodeByRef($screen->tree(), 'header-alert-badge');
    expect($badge)->not->toBeNull();
    // position_type 1 = absolute (see TailwindParser's `absolute` class) —
    // proves this is an overlapping corner badge, not the old inline pill
    // laid out in normal (relative) flow next to the bell icon.
    expect($badge['layout']['position_type'] ?? null)->toBe(1);
    expect($badge['layout']['position'] ?? null)->toBe([-3.0, -3.0, 0.0, 0.0]);
});
