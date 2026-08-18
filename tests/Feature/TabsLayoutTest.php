<?php

use App\Models\LocalState;
use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Screens\Dashboard;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Platform;
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

it('resolves a shopping-cart icon on the Ventes tab, matching the mockup, on both platforms', function () {
    // Regression test: this tab used to be `icon: 'eurosign.circle'` — a
    // currency-circle glyph with no equivalent in the mockup, which also
    // has no manual Android name mapping and silently renders as a
    // missing glyph on that platform. It was briefly `Ios::Bag` /
    // `AndroidOutlined::ShoppingBag`, but SF Symbols aren't stroke-drawn
    // like the mockup's SVGs — `bag` renders as a dense, mostly-solid
    // glyph that visually clashes with the thin-line house/chart/bulb/bell
    // icons beside it on iOS. `cart` is a wirier glyph matching that
    // weight, so this pins the resolved icon per platform.
    fakeTabsLayoutEndpoints();
    $screen = Native::test(Dashboard::class);
    $registry = new CallbackRegistry;

    // Tab::toElement() resolves the platform icon triple eagerly (via
    // HasPlatformIcon::resolvedIcon()) at build time, not lazily at
    // render time — so the platform must be set before tabBar() runs,
    // not before reading the already-built item's resolved props.
    try {
        Platform::set('ios');
        $iosItem = (new TabsLayout)->tabBar($screen->instance())->tabElements()[2];
        expect($iosItem->getResolvedProps($registry)['icon'] ?? null)->toBe('cart');

        Platform::set('android');
        $androidItem = (new TabsLayout)->tabBar($screen->instance())->tabElements()[2];
        $androidProps = $androidItem->getResolvedProps($registry);
        expect($androidProps['icon'] ?? null)->toBe('shopping_cart');
        expect($androidProps['material_variant'] ?? null)->toBe('outlined');
    } finally {
        Platform::set(null);
    }
});

it('renders the shared header with the cached shop name from LocalState', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['active_shop_name' => 'Boutique Principale']);

    // Native::test() only applies native chrome (navBar/tabBar) when a
    // layout is passed explicitly — unlike Native::visit(), which resolves
    // it from the route registration. Without this, the header partial
    // never renders and the assertion below passes vacuously for the
    // wrong reason.
    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    $screen->assertSee('Boutique Principale');
});

it('shows the unread alert count as a badge on the Alertes tab', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['unread_alert_count' => 3]);
    $screen = Native::test(Dashboard::class);

    $alertsItem = (new TabsLayout)->tabBar($screen->instance())->tabElements()[4];
    $props = $alertsItem->getResolvedProps(new CallbackRegistry);

    expect($props['badge'] ?? null)->toBe('3');
});

it('shows no badge on the Alertes tab when unread_alert_count is zero', function () {
    fakeTabsLayoutEndpoints();
    LocalState::current()->update(['unread_alert_count' => 0]);
    $screen = Native::test(Dashboard::class);

    $alertsItem = (new TabsLayout)->tabBar($screen->instance())->tabElements()[4];
    $props = $alertsItem->getResolvedProps(new CallbackRegistry);

    expect($props['badge'] ?? null)->toBeNull();
});

it('renders the shop-pill logo image', function () {
    fakeTabsLayoutEndpoints();

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    expect(findNodeByRef($screen->tree(), 'header-shop-logo'))->not->toBeNull();
});

it('chains ->minWidth() onto the header partial\'s own root row, not an implicit wrapper', function () {
    // Regression test for HasHeaderChrome::headerTitleView(): it calls
    // ->minWidth() on whatever fromViewPartial() returns, trusting that's
    // the partial's own `ref="header-shop-row"` root. If collect() ever
    // started wrapping single-root partials in an implicit Column, the
    // chain would silently land on that wrapper instead and the ref'd row
    // would carry no min_width — this only catches that failure mode; it
    // says nothing about whether the width is enough to actually beat
    // SwiftUI's `.principal`-slot centering on device.
    fakeTabsLayoutEndpoints();

    $screen = Native::test(Dashboard::class, layout: TabsLayout::class);

    $row = findNodeByRef($screen->tree(), 'header-shop-row');
    expect($row)->not->toBeNull();
    expect($row['layout']['min_width'] ?? null)->toBe(260.0);
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

it('wires the account and alerts icons as trailing bar actions, not into the cramped title slot', function () {
    // Regression test: the account/alerts buttons used to be composed
    // inside the header partial's own titleView markup, sharing the bar's
    // narrow, centered `.principal` slot with the shop-switcher pill — a
    // `w-full justify-between` row fighting for space that slot doesn't
    // have, which is what made the header look cramped. They now render
    // as real NavBar actions, which get guaranteed trailing space of
    // their own.
    fakeTabsLayoutEndpoints();
    $screen = Native::test(Dashboard::class);

    $navBar = (new TabsLayout)->navBar($screen->instance());
    $registry = new CallbackRegistry;
    $actions = collect($navBar->actionElements())
        ->map(fn ($action) => $action->toArray($registry))
        ->keyBy(fn (array $node) => $node['props']['id'] ?? null);

    expect($actions->has('account'))->toBeTrue();
    expect($actions['account']['props']['icon'] ?? null)->toBe('person');
    expect($actions['account']['props']['a11y_label'] ?? null)->toBe('Mon compte');
    expect($actions['account']['on_press'] ?? null)->toBe($registry->register('openAccountSheet'));

    expect($actions->has('alerts'))->toBeTrue();
    expect($actions['alerts']['props']['icon'] ?? null)->toBe('bell');
    expect($actions['alerts']['props']['a11y_label'] ?? null)->toBe('Alertes');
    expect($actions['alerts']['on_press'] ?? null)->toBe($registry->register('goAlerts'));
});
