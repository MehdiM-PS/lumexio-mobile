<?php

use App\Models\LocalState;
use App\NativeComponents\Screens\Dashboard;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

afterEach(function () {
    Carbon::setTestNow();
});

function fakeDashboardEndpoints(array $shops = [], ?array $widgets = null, ?array $topProducts = null, ?array $user = null): void
{
    Http::fake([
        '*/auth/me*' => Http::response(['user' => $user ?? ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/dashboard/metrics*' => Http::response(['metrics' => [
            'revenue_today' => 320.5,
            'orders_today' => 4,
            'revenue_period' => 8450.0,
            'orders_period' => 96,
            'avg_order_value' => 88.02,
            'products_count' => 214,
            'low_stock_count' => 6,
            'customers_count' => 312,
        ]], 200),
        '*/dashboard/charts*' => Http::response([
            'revenue_margin' => ['labels' => ['01/08', '02/08'], 'revenue' => [100.0, 250.0], 'margin' => [40.0, 90.0]],
            'stock' => ['labels' => ['01/08', '02/08'], 'quantity' => [500, 480], 'value' => [12000.0, 11500.0]],
        ], 200),
        '*/dashboard/recent-orders*' => Http::response(['orders' => [
            ['id' => 1, 'reference' => 'ORD001', 'total_paid' => 45.9, 'order_date' => '2026-08-13T10:00:00Z',
                'customer' => ['firstname' => 'Jean', 'lastname' => 'Dupont']],
        ]], 200),
        '*/dashboard/low-stock*' => Http::response(['products' => [
            ['id' => 1, 'name' => 'T-shirt bleu', 'quantity' => 2, 'low_stock_threshold' => 5],
        ]], 200),
        '*/dashboard/widgets*' => Http::response(['widgets' => $widgets ?? [
            'day' => 'today',
            'date' => today()->toDateString(),
            'ca_forecast_percent' => 62.5,
            'ca_forecast_predicted' => 800.0,
            'avg_cart' => 45.0,
            'avg_cart_comparison' => 40.0,
            'avg_cart_change' => 12.5,
            'new_customers' => 3,
            'week_revenue' => 1200.0,
            'week_trend' => 8.0,
        ]], 200),
        '*/dashboard/top-products*' => Http::response([
            'day' => 'today',
            'date' => today()->toDateString(),
            'products' => $topProducts ?? [
                ['product_id' => 7, 'name' => 'Chaise design', 'image_url' => 'https://example.test/chaise.jpg', 'category' => 'Mobilier', 'quantity' => 4, 'revenue' => 199.99],
            ],
        ], 200),
        '*/forecasts*' => Http::response([
            'historical' => [
                ['date' => '2026-08-01', 'revenue' => 100.0],
                ['date' => '2026-08-02', 'revenue' => 150.0],
            ],
            'forecasts' => [
                ['forecast_date' => '2026-08-15', 'predicted_revenue' => 200.0, 'confidence_score' => 85],
            ],
            'summary' => ['forecast_7d' => 1200.0, 'forecast_30d' => 5000.0, 'historical_7d' => 1100.0, 'historical_30d' => 4800.0, 'trend' => 'up', 'confidence' => 82],
        ], 200),
        '*/shops*' => Http::response(['shops' => $shops ?: [
            ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'idle', 'sync_error' => null],
        ]], 200),
    ]);
}

it('shows the dashboard tab bar and KPI data', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertHasTab('Dashboard')
        ->assertTabActive('Dashboard')
        ->assertSee('8 450') // revenue_period, formatted
        ->assertSee('96') // orders_period
        ->assertSee('312') // customers_count
        ->assertSee('Jean Dupont')
        ->assertSee('T-shirt bleu')
        ->assertElement('rect')
        // The embedded <native:forecast-section /> reads summary.confidence
        // from the real GET /forecasts shape (see fakeDashboardEndpoints'
        // '*/forecasts*' fixture) — asserts real data renders, not the "0%"
        // that a mismatched fixture shape would silently degrade to.
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence' && str_contains($n['props']['text'] ?? '', '82'));
});

it('refetches data on pull-to-refresh', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')->call('refresh');

    Http::assertSentCount(17); // 8 dashboard (incl. auth/me) + 1 forecast on mount, 8 dashboard on refresh (forecast doesn't refresh automatically)
});

it('is fully accessible', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')->assertAccessible();
});

it('shows a generic error instead of crashing when the network is unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    Native::visit('/dashboard')->assertSee('Connexion indisponible');
});

it('shows a generic error instead of crashing when the charts endpoint returns a 500', function () {
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/dashboard/charts*' => Http::response(['message' => 'Server Error'], 500),
        '*/dashboard/metrics*' => Http::response(['metrics' => [
            'revenue_today' => 320.5,
            'orders_today' => 4,
            'revenue_period' => 8450.0,
            'orders_period' => 96,
            'avg_order_value' => 88.02,
            'products_count' => 214,
            'low_stock_count' => 6,
            'customers_count' => 312,
        ]], 200),
        '*/dashboard/recent-orders*' => Http::response(['orders' => [
            ['id' => 1, 'reference' => 'ORD001', 'total_paid' => 45.9, 'order_date' => '2026-08-13T10:00:00Z',
                'customer' => ['firstname' => 'Jean', 'lastname' => 'Dupont']],
        ]], 200),
        '*/dashboard/low-stock*' => Http::response(['products' => [
            ['id' => 1, 'name' => 'T-shirt bleu', 'quantity' => 2, 'low_stock_threshold' => 5],
        ]], 200),
        '*/dashboard/widgets*' => Http::response(['widgets' => [
            'day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 62.5,
            'ca_forecast_predicted' => 800.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0,
            'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0,
        ]], 200),
        '*/dashboard/top-products*' => Http::response(['day' => 'today', 'date' => today()->toDateString(), 'products' => []], 200),
        '*/forecasts*' => Http::response([
            'historical' => [],
            'forecasts' => [],
            'summary' => [],
        ], 200),
        '*/shops*' => Http::response(['shops' => []], 200),
    ]);

    Native::visit('/dashboard')->assertSee('Server Error');
});

it('shows the sync error message when the current shop failed to sync', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'failed', 'sync_error' => 'Failed to fetch customers from module'],
    ]);

    Native::visit('/dashboard')
        ->assertSee('Failed to fetch customers from module')
        ->assertAccessible();
});

it('shows a generic fallback message when the sync failed without a sync_error', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'failed', 'sync_error' => null],
    ]);

    Native::visit('/dashboard')->assertSee('synchronisation a échoué');
});

it('shows a syncing banner when the current shop is syncing', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'syncing', 'sync_error' => null],
    ]);

    Native::visit('/dashboard')
        ->assertSee('Synchronisation en cours')
        ->assertAccessible();
});

it('shows no sync banner when the current shop is idle', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'idle', 'sync_error' => null],
    ]);

    Native::visit('/dashboard')
        ->assertDontSee('synchronisation a échoué')
        ->assertDontSee('Synchronisation en cours');
});

it('shows no sync banner when the current shop is not found in the fetched shops list', function () {
    LocalState::current()->update(['shop_id' => 'shop-missing']);
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'failed', 'sync_error' => 'Some error'],
    ]);

    Native::visit('/dashboard')
        ->assertDontSee('synchronisation a échoué')
        ->assertDontSee('Synchronisation en cours')
        ->assertDontSee('Some error');
});

it('defaults to today and requests metrics with day=today when opened at or after 14:00', function () {
    // Mirrors web's DashboardPage::DAY_SWITCH_HOUR heuristic: from 14:00
    // onward, today's figures are complete enough to default to J.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');

    expect($screen->get('dayScope'))->toBe(0);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'today');
});

it('defaults to yesterday and requests metrics with day=yesterday when opened before 14:00', function () {
    // Parity fix: before 14:00 today's figures are too partial to be
    // meaningful, so mobile now defaults to yesterday (J-1), exactly like
    // web's DashboardPage::DAY_SWITCH_HOUR heuristic — a merchant opening
    // the app at 9am must see the same default as on web at the same moment.
    Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');

    expect($screen->get('dayScope'))->toBe(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'yesterday');
});

it('switches to yesterday and re-fetches metrics with day=yesterday', function () {
    // Frozen to the afternoon so the default day scope is "today" — otherwise
    // this toggle would be a no-op before 14:00 and the assertions below
    // would pass vacuously without setDayScope() having changed anything.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->call('setDayScope', 1);

    expect($screen->get('dayScope'))->toBe(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'yesterday');
});

it('shows a two-option day-scope toggle bound to setDayScope', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertElement('button_group', fn (array $n): bool => ($n['props']['on_change'] ?? null) === callbackIdFor('setDayScope'));
});

it('re-fetches metrics with day=yesterday when the on-device toggle fires a real change event', function () {
    // Regression guard for the @press-on-Chip trap documented in this repo's
    // git history: a bound on_change id alone doesn't prove the tap works.
    // This drives the actual NativeComponent::dispatch() path (the harness's
    // `fireEvent` primitive, same as a real device tap) instead of calling
    // setDayScope() directly, so it would fail if setDayScope(int $index)
    // ever stopped receiving the tapped index as an argument.
    // Frozen to the afternoon so the default day scope is "today" — otherwise
    // this fires before 14:00, the mount default is already "yesterday", and
    // the guard below would pass without the tap having done anything.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->fireEvent('dashboard-day-scope', $screen::EVENT_TAB_CHANGE, ['value' => 1]);

    expect($screen->get('dayScope'))->toBe(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'yesterday');
});

it('switches the hero card day label from Aujourd\'hui to Hier when dayScope changes', function () {
    // Asserted on the label's own `ref`, not a plain assertSee('Hier') — the
    // button-group toggle's own :options="['Aujourd\'hui', 'Hier']" already
    // renders the literal string "Hier" elsewhere on this screen, so a
    // substring assertion would pass vacuously even if the hero label were
    // still hardcoded to "Aujourd'hui".
    // Frozen to the afternoon so the default day scope is "today" (see the
    // DAY_SWITCH_HOUR heuristic) — this test is about toggling, not defaults.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-hero-day-label'
        && ($n['props']['text'] ?? null) === "Aujourd'hui");

    $screen->call('setDayScope', 1);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-hero-day-label'
        && ($n['props']['text'] ?? null) === 'Hier');
});

it('fetches widgets scoped to the current day and stores them', function () {
    // Frozen to the afternoon so the default day scope is "today" — otherwise
    // the mount fetch would already be day=yesterday and the assertion below
    // would pass without setDayScope() having triggered a re-fetch.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints(widgets: ['day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 62.5, 'ca_forecast_predicted' => 800.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0, 'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0]);

    $screen = Native::test(Dashboard::class);

    expect($screen->get('widgets')['ca_forecast_percent'])->toBe(62.5);

    $screen->call('setDayScope', 1);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/widgets')
        && ($request['day'] ?? null) === 'yesterday');
});

it('renders the forecast progress bar scaled to a 0-1 fraction, clamped at 100 percent', function () {
    fakeDashboardEndpoints(widgets: ['day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 150.0, 'ca_forecast_predicted' => 800.0, 'avg_cart' => 0, 'avg_cart_comparison' => 0, 'avg_cart_change' => null, 'new_customers' => 0, 'week_revenue' => 0, 'week_trend' => 0]);

    Native::test(Dashboard::class)
        ->assertElement('progress_bar', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-forecast-progress' && ($n['props']['value'] ?? null) === 1.0);
});

it('shows a fallback label instead of the progress bar when the forecast is unavailable', function () {
    fakeDashboardEndpoints(widgets: ['day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => null, 'ca_forecast_predicted' => null, 'avg_cart' => 0, 'avg_cart_comparison' => 0, 'avg_cart_change' => null, 'new_customers' => 0, 'week_revenue' => 0, 'week_trend' => 0]);

    Native::test(Dashboard::class)
        ->assertMissingElement('progress_bar', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-forecast-progress')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-forecast-unavailable');
});

it('renders the 3 new KPI tiles with null-safe avg cart change', function () {
    fakeDashboardEndpoints(widgets: ['day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 50.0, 'ca_forecast_predicted' => 1000.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 0.0, 'avg_cart_change' => null, 'new_customers' => 7, 'week_revenue' => 500.0, 'week_trend' => -12.5]);

    Native::test(Dashboard::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-new-customers-value' && ($n['props']['text'] ?? null) === '7')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-week-trend-value' && str_contains((string) ($n['props']['text'] ?? ''), '-12,5'));
});

it('fetches and stores the top products for the current day', function () {
    // Frozen to the afternoon so the default day scope is "today" — otherwise
    // the mount fetch would already be day=yesterday and the assertion below
    // would pass without setDayScope() having triggered a re-fetch.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints(topProducts: [
        ['product_id' => 1, 'name' => 'T-shirt', 'image_url' => null, 'category' => 'Vêtements', 'quantity' => 5, 'revenue' => 150.0],
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->get('topProducts'))->toHaveCount(1);

    $screen->call('setDayScope', 1);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/top-products')
        && ($request['day'] ?? null) === 'yesterday');
});

it('renders a ranked row per top product', function () {
    fakeDashboardEndpoints(topProducts: [
        ['product_id' => 1, 'name' => 'T-shirt', 'image_url' => null, 'category' => 'Vêtements', 'quantity' => 5, 'revenue' => 150.0],
    ]);

    Native::test(Dashboard::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-top-product-1-name' && ($n['props']['text'] ?? null) === 'T-shirt');
});

it('shows an empty state when there are no top products', function () {
    fakeDashboardEndpoints(topProducts: []);

    Native::test(Dashboard::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-top-products-empty');
});

it('switches the top-products empty state from today to yesterday when dayScope changes', function () {
    // Asserted on the empty state's own `ref`, not a plain assertSee('Hier') /
    // assertSee('aujourd\'hui') — the button-group toggle's own
    // :options="['Aujourd\'hui', 'Hier']" already renders both literal
    // strings elsewhere on this screen, so a substring assertion would pass
    // vacuously even if this text were still hardcoded to "aujourd'hui".
    // Same trap documented on the hero day label test above.
    // Frozen to the afternoon so the default day scope is "today" (see the
    // DAY_SWITCH_HOUR heuristic) — this test is about toggling, not defaults.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints(topProducts: []);

    $screen = Native::test(Dashboard::class);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-top-products-empty'
        && ($n['props']['text'] ?? null) === "Aucune vente aujourd'hui.");

    $screen->call('setDayScope', 1);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-top-products-empty'
        && ($n['props']['text'] ?? null) === 'Aucune vente hier.');
});

it('does not show the revenue chart tooltip before any bar is tapped', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-tooltip');
});

it('shows the tapped bar\'s date and revenue in the chart tooltip', function () {
    // fakeDashboardEndpoints()'s revenue_margin chart: labels ['01/08', '02/08'],
    // revenue [100.0, 250.0] — tapping index 1 must surface exactly that pair.
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->call('selectBar', 1);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-tooltip'
        && ($n['props']['text'] ?? null) === '02/08 — 250,00 €');
});

it('wires each revenue bar to selectBar with its own baked-in index', function () {
    // Element::toArray() registers `@press`'s pressMethod as a top-level
    // `on_press` field regardless of whether the element type actually
    // reads it (the same trap documented for Chip's `@press`/on_change
    // mismatch elsewhere in this suite) — so this only proves something if
    // the id is compared against the exact per-bar expression, not just
    // isset(). It also proves the index is genuinely baked per-bar: a view
    // where every rect carried `@press="selectBar(0)"` would still pass an
    // isset()-only check but fails this one, since bar-1's id would then
    // equal callbackIdFor('selectBar(0)') instead of ('selectBar(1)').
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-bar-0'
            && ($n['on_press'] ?? null) === callbackIdFor('selectBar(0)'))
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-bar-1'
            && ($n['on_press'] ?? null) === callbackIdFor('selectBar(1)'));
});

it('gives each revenue bar an a11y-label announcing its date and value', function () {
    // assertAccessible() has no audit rule for the `rect` type (verified by
    // reading TestableComponent::collectA11yViolations()), so it would stay
    // green even if these labels were missing entirely — this asserts the
    // resolved `a11y_label` prop directly instead, on both bars, so a
    // regression that drops or misindexes the label is actually caught.
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-bar-0'
            && ($n['props']['a11y_label'] ?? null) === '01/08 — 100,00 €')
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-bar-1'
            && ($n['props']['a11y_label'] ?? null) === '02/08 — 250,00 €');
});

it('deselects the revenue bar and hides the tooltip when tapped a second time', function () {
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->call('selectBar', 1);

    // Confirm the tooltip is genuinely showing between the two taps, so this
    // test fails if selectBar() is broken outright (not only if the toggle-
    // off branch specifically regresses).
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-tooltip');

    $screen->call('selectBar', 1);

    expect($screen->get('selectedBarIndex'))->toBeNull();
    $screen->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-chart-tooltip');
});

it('gives the selected revenue bar a different style than an unselected bar', function () {
    // Before any tap all bars share the same (unselected) style; after
    // selecting bar 0 only that bar's background should change — proves
    // the conditional class is actually wired to $selectedBarIndex rather
    // than always-on or always-off.
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $before = $screen->tree();
    $bar0Before = findNodeByRef($before, 'dashboard-chart-bar-0');
    $bar1Before = findNodeByRef($before, 'dashboard-chart-bar-1');

    expect($bar0Before)->not->toBeNull();
    expect($bar1Before)->not->toBeNull();
    expect($bar0Before['style']['bg_color'] ?? null)->toBe($bar1Before['style']['bg_color'] ?? null);

    $screen->call('selectBar', 0);

    $after = $screen->tree();
    $bar0After = findNodeByRef($after, 'dashboard-chart-bar-0');
    $bar1After = findNodeByRef($after, 'dashboard-chart-bar-1');

    expect($bar0After['style']['bg_color'] ?? null)
        ->not->toBe($bar1After['style']['bg_color'] ?? null)
        ->not->toBe($bar0Before['style']['bg_color'] ?? null);
});

it('resets the selected bar index when the chart data is refreshed', function () {
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->call('selectBar', 0);

    expect($screen->get('selectedBarIndex'))->toBe(0);

    fakeDashboardEndpoints();
    $screen->call('refresh');

    expect($screen->get('selectedBarIndex'))->toBeNull();
});

it('opens and closes the shop switcher sheet, loading shops on open', function () {
    // Shops passed via fakeDashboardEndpoints() itself — Http::fake() stubs
    // are matched in registration order (first matching pattern wins), so a
    // second, separately-registered '*/shops' fake here would never actually
    // override fakeDashboardEndpoints()'s own broader '*/shops*' stub.
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Boutique Principale', 'platform' => 'prestashop', 'revenue_delta_percent' => 12.0],
    ]);

    $screen = Native::test(Dashboard::class);

    // Forward guard: the `shop-switcher-sheet` element itself is added by the
    // sheet partial in Task 4, so this resolves null ?? null and is vacuous
    // today — it goes live once that partial exists on this screen.
    expect(findNodeByRef($screen->tree(), 'shop-switcher-sheet')['props']['visible'] ?? null)->toBeFalsy();

    $screen->call('openShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeTrue();
    expect($screen->get('switcherShops'))->toHaveCount(1);
    // Distinguishes the switcher's own /shops fetch from refresh()'s
    // separate /shops call (used for the sync-status banner).
    expect($screen->get('switcherShops')[0]['name'])->toBe('Boutique Principale');

    $screen->call('closeShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeFalse();
});

it('opens the account sheet and closes the shop sheet if it was open', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('openShopSwitcher');
    expect($screen->get('shopSheetOpen'))->toBeTrue();

    $screen->call('openAccountSheet');

    expect($screen->get('accountSheetOpen'))->toBeTrue()
        ->and($screen->get('shopSheetOpen'))->toBeFalse();
});

it('selects a shop, updates LocalState, and replaces to the dashboard', function () {
    // Shops passed via fakeDashboardEndpoints() itself — see the comment in
    // the "opens and closes the shop switcher sheet" test above for why a
    // separately-registered '*/shops' fake wouldn't take effect here.
    fakeDashboardEndpoints([
        ['id' => 'shop-2', 'name' => 'Boutique Client A', 'platform' => 'shopify', 'revenue_delta_percent' => 8.2],
    ]);

    $screen = Native::test(Dashboard::class);
    $screen->call('openShopSwitcher');

    $screen->call('selectShop', 'shop-2');

    expect(LocalState::current()->fresh()->shop_id)->toBe('shop-2')
        ->and(LocalState::current()->fresh()->active_shop_name)->toBe('Boutique Client A');
    $screen->assertReplacedWith('/dashboard');
});

it('navigates to alerts via goAlerts', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('goAlerts');

    $screen->assertNavigatedTo('/alerts');
});

it('loads the current user and computes account name/email/initials', function () {
    // The user fixture goes through fakeDashboardEndpoints()'s own `user`
    // param rather than a second, separately-registered Http::fake() call —
    // Http::fake() stubs are matched in registration order (first matching
    // pattern wins), so a second '*/auth/me' fake here would never actually
    // override fakeDashboardEndpoints()'s own '*/auth/me*' stub.
    fakeDashboardEndpoints(user: ['name' => 'Marie Chevalier', 'email' => 'marie@boutique-principale.fr']);

    $screen = Native::test(Dashboard::class);
    $screen->call('loadCurrentUser');

    expect($screen->instance()->accountName())->toBe('Marie Chevalier')
        ->and($screen->instance()->accountEmail())->toBe('marie@boutique-principale.fr')
        ->and($screen->instance()->accountInitials())->toBe('MC');
});

it('renders the shop switcher sheet with each shop and highlights the active one', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    // Shops passed via fakeDashboardEndpoints() itself — see the comment on
    // the "opens and closes the shop switcher sheet" test above for why a
    // separately-registered '*/shops' fake wouldn't take effect here.
    fakeDashboardEndpoints([
        ['id' => 'shop-1', 'name' => 'Boutique Principale', 'platform' => 'prestashop', 'revenue_delta_percent' => 12.0],
        ['id' => 'shop-2', 'name' => 'Boutique Client A', 'platform' => 'shopify', 'revenue_delta_percent' => -1.4],
    ]);

    $screen = Native::test(Dashboard::class);
    $screen->call('openShopSwitcher');

    $tree = $screen->tree();
    $sheet = findNodeByRef($tree, 'shop-switcher-sheet');

    expect($sheet['props']['visible'] ?? null)->toBeTruthy();
    $row1 = findNodeByRef($tree, 'shop-switcher-row-shop-1');
    $row2 = findNodeByRef($tree, 'shop-switcher-row-shop-2');
    expect($row1)->not->toBeNull();
    expect($row2)->not->toBeNull();

    // "highlights the active one": the active shop (shop-1, set via
    // LocalState above) must render with a different background than the
    // inactive one — not just that both rows exist. Same style-comparison
    // pattern as the "gives the selected revenue bar a different style"
    // test above.
    expect($row1['style']['bg_color'] ?? null)
        ->not->toBe($row2['style']['bg_color'] ?? null);
});

it('renders the account sheet with the user name and email', function () {
    // User fixture via fakeDashboardEndpoints()'s own `user` param — see the
    // stub-ordering comment on the "loads the current user" test above.
    fakeDashboardEndpoints(user: ['name' => 'Marie Chevalier', 'email' => 'marie@boutique-principale.fr']);

    $screen = Native::test(Dashboard::class);
    $screen->call('openAccountSheet');

    $tree = $screen->tree();

    expect(findNodeByRef($tree, 'account-sheet')['props']['visible'] ?? null)->toBeTruthy();
    // Asserted on the resolved `text` prop, not just presence — the sheet is
    // always in the tree regardless of `visible`, so an isset()-only check
    // would pass even if Dashboard::refresh() never called loadCurrentUser()
    // and these fields rendered empty.
    expect(findNodeByRef($tree, 'account-sheet-name')['props']['text'] ?? null)->toBe('Marie Chevalier');
    expect(findNodeByRef($tree, 'account-sheet-email')['props']['text'] ?? null)->toBe('marie@boutique-principale.fr');
});

it('navigates to stock and orders from the account sheet', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('goStock');
    $screen->assertNavigatedTo('/stock');

    $screen = Native::test(Dashboard::class);
    $screen->call('goOrders');
    $screen->assertNavigatedTo('/orders');
});
