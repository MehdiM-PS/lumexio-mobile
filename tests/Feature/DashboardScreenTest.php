<?php

use App\Models\LocalState;
use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Screens\Dashboard;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

afterEach(function () {
    Carbon::setTestNow();
});

function fakeDashboardEndpoints(array $shops = [], ?array $widgets = null, ?array $user = null, ?array $summary = null, ?array $stockStats = null, ?array $segments = null, ?array $alerts = null): void
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
        '*/dashboard/widgets*' => Http::response(['widgets' => $widgets ?? [
            'day' => 'today',
            'date' => today()->toDateString(),
            'ca_forecast_percent' => 112.0,
            'ca_forecast_predicted' => 286.0,
            'avg_cart' => 45.0,
            'avg_cart_comparison' => 40.0,
            'avg_cart_change' => 12.5,
            'new_customers' => 3,
            'week_revenue' => 1200.0,
            'week_trend' => 8.0,
        ]], 200),
        '*/forecasts*' => Http::response([
            'historical' => [],
            'forecasts' => [],
            'summary' => $summary ?? ['forecast_30d' => 87000.0, 'forecast_7d' => 20300.0, 'confidence' => 92, 'historical_7d' => 19000.0, 'historical_30d' => 84000.0, 'trend' => 'up'],
        ], 200),
        '*/stock-depletion*' => Http::response([
            'stats' => $stockStats ?? ['total_products' => 214, 'out_of_stock' => 1, 'critical' => 3, 'avg_days_until_stockout' => 12, 'total_stock_value' => 45000.0],
            'products' => [],
        ], 200),
        '*/segments/stats*' => Http::response([
            'segments' => $segments ?? [
                ['name' => 'vip', 'customers_count' => 128],
                ['name' => 'at_risk', 'customers_count' => 6],
            ],
        ], 200),
        '*/alerts*' => Http::response(['alerts' => $alerts ?? [
            ['id' => 1, 'severity' => 'critical', 'title' => 'Anomalie détectée', 'message' => 'Ventes en baisse de 34%.', 'created_at' => now()->subHours(2)->toIso8601String()],
        ]], 200),
        '*/shops*' => Http::response(['shops' => $shops ?: [
            ['id' => 'shop-1', 'name' => 'Ma Boutique', 'sync_status' => 'idle', 'sync_error' => null],
        ]], 200),
    ]);
}

it('shows the dashboard tab bar and KPI data', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertHasTab('Accueil')
        ->assertTabActive('Accueil')
        ->assertSee('320,50') // revenue_today, formatted
        ->assertSee('87 000') // forecast_30d
        ->assertSee('92') // confidence
        ->assertSee('3') // critical stock count
        ->assertSee('128') // vip count
        ->assertSee('Anomalie détectée'); // recent alert title
});

// The actual motivating scenario for LumexioApi's GET cache: quickly
// switching away from and back to a tab remounts the screen (mount() runs
// again), but within the short TTL window it should replay cached
// responses instead of re-hitting the network — this is what makes fast
// tab-switching cheap while refresh() (tested above) still always refetches.
it('serves a second mount within the cache TTL from cache instead of refetching', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard');
    Native::visit('/dashboard');

    Http::assertSentCount(8); // one dashboard-area round trip total, not two
});

it('refetches data on pull-to-refresh', function () {
    fakeDashboardEndpoints();

    // refresh() (bound to pull-to-refresh) always busts the short-lived GET
    // cache before reloading, so it actually re-hits the network instead of
    // replaying mount()'s cached responses.
    Native::visit('/dashboard')->call('refresh');

    Http::assertSentCount(16); // 8 dashboard-area calls (incl. auth/me) on mount, 8 on refresh — same total as before Slice C, different endpoint mix (forecasts/stock-depletion/segments/alerts replaced charts/recent-orders/low-stock/top-products).
});

it('is fully accessible', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')->assertAccessible();
});

it('shows a generic error instead of crashing when the network is unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    Native::visit('/dashboard')->assertSee('Connexion indisponible');
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

it('switches the hero card day label from CA aujourd\'hui to CA d\'hier when dayScope changes', function () {
    // Asserted on the label's own `ref`, not a plain assertSee — the
    // button-group toggle's own :options="['Aujourd\'hui', 'Hier']" already
    // renders those literal strings elsewhere on this screen, so a
    // substring assertion would pass vacuously even if the hero label were
    // still hardcoded. Copy per the mockup (line 82): "CA {{dayLabelLower}}".
    // Frozen to the afternoon so the default day scope is "today" (see the
    // DAY_SWITCH_HOUR heuristic) — this test is about toggling, not defaults.
    Carbon::setTestNow(Carbon::parse('2026-08-14 15:00:00'));
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-hero-day-label'
        && ($n['props']['text'] ?? null) === "CA aujourd'hui");

    $screen->call('setDayScope', 1);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-hero-day-label'
        && ($n['props']['text'] ?? null) === "CA d'hier");
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

it('shows a positive hero delta vs the day forecast', function () {
    fakeDashboardEndpoints(widgets: [
        'day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 112.0,
        'ca_forecast_predicted' => 286.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0,
        'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0,
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->heroDeltaPercent())->toBe(12.0);
    // Mock (line 85 / 832): "↑ 12%" — an arrow, not a "+" sign.
    $screen->assertSee('↑ 12%');
});

it('shows a negative hero delta vs the day forecast', function () {
    fakeDashboardEndpoints(widgets: [
        'day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 88.0,
        'ca_forecast_predicted' => 364.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0,
        'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0,
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->heroDeltaPercent())->toBe(-12.0);
    // The mock hardcodes "↑" and never demonstrates a down day, but
    // heroDeltaPercent() can go negative — the arrow must flip, not lie.
    $screen->assertSee('↓ 12%');
});

it('normalizes a delta that rounds to zero so it never renders as a down arrow', function () {
    // ca_forecast_percent=99.8 gives a raw delta of -0.2 — without the
    // abs(...) < 0.5 normalization in heroDeltaPercent(), number_format on
    // that raw value would render a "↓ 0%" in destructive red, which is
    // cosmetically wrong (this isn't the "no forecast" case, which is
    // already handled separately by the null gate above). heroDeltaPercent()
    // normalizes to 0.0, and the blade's `>= 0` branch treats that as "up".
    fakeDashboardEndpoints(widgets: [
        'day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 99.8,
        'ca_forecast_predicted' => 300.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0,
        'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0,
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->heroDeltaPercent())->toBe(0.0);
    $screen->assertSee('↑ 0%')->assertDontSee('↓ 0%');
});

it('hides the hero delta when the day forecast is unavailable', function () {
    fakeDashboardEndpoints(widgets: [
        'day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => null,
        'ca_forecast_predicted' => 0.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0,
        'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0,
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->heroDeltaPercent())->toBeNull();
    $tree = $screen->tree();
    expect(findNodeByRef($tree, 'dashboard-hero-delta'))->toBeNull();
});

it('renders the 3 new KPI cards with their sub-labels', function () {
    fakeDashboardEndpoints(
        summary: ['forecast_30d' => 87000.0, 'forecast_7d' => 20300.0, 'confidence' => 92, 'historical_7d' => 19000.0, 'historical_30d' => 84000.0, 'trend' => 'up'],
        stockStats: ['total_products' => 214, 'out_of_stock' => 1, 'critical' => 3, 'avg_days_until_stockout' => 12, 'total_stock_value' => 45000.0],
        segments: [['name' => 'vip', 'customers_count' => 128], ['name' => 'at_risk', 'customers_count' => 6]],
    );

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->forecast30d())->toBe(87000.0);
    expect($screen->instance()->forecastConfidence())->toBe(92);
    expect($screen->instance()->criticalStockCount())->toBe(3);
    expect($screen->instance()->vipCount())->toBe(128);
    expect($screen->instance()->atRiskCount())->toBe(6);

    $screen->assertSee('87 000')->assertSee('92')->assertSee('128');

    // assertSee('3') and assertSee('6') are satisfied by unrelated text
    // elsewhere on the screen (e.g. '3' also appears in '320,50 €'), so they
    // don't actually prove the critical-stock/at-risk KPI values render
    // correctly. Assert on the rendered elements themselves instead.
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-kpi-critical-stock'
        && ($n['props']['text'] ?? null) === '3');
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-kpi-at-risk'
        && ($n['props']['text'] ?? null) === '6 à risque');
});

it('lays out the KPI cards as the mock does: forecast alone on its own row, ruptures + VIP sharing the next row', function () {
    // Mock's kpis array (line 838) gives "Prévision 30j" grid-column: span 2
    // (full width, alone on row 1) with "Ruptures prévues" and "Clients VIP"
    // sharing row 2 — not a 2-up/1-alone split.
    fakeDashboardEndpoints();

    $tree = Native::test(Dashboard::class, layout: TabsLayout::class)->tree();

    $forecastRow = findNodeByRef($tree, 'dashboard-kpi-row-forecast');
    $secondaryRow = findNodeByRef($tree, 'dashboard-kpi-row-secondary');

    expect($forecastRow)->not->toBeNull();
    expect($secondaryRow)->not->toBeNull();
    expect(findNodeByRef($forecastRow, 'dashboard-kpi-forecast-30d'))->not->toBeNull();
    expect(findNodeByRef($forecastRow, 'dashboard-kpi-critical-stock'))->toBeNull();
    expect(findNodeByRef($secondaryRow, 'dashboard-kpi-critical-stock'))->not->toBeNull();
    expect(findNodeByRef($secondaryRow, 'dashboard-kpi-vip'))->not->toBeNull();
});

it('colors the "Ruptures prévues" sub-label with the accent token, matching the mock\'s warning tone', function () {
    // Mock (line 839): subColor: oklch(0.7 0.17 55) — the same hue as
    // config('native-ui.theme.light.accent') (#EC7C0E), not the neutral
    // on-surface-variant gray the KPI card's other sub-labels use.
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);

    $screen->assertElement('text', fn (array $n): bool => ($n['props']['text'] ?? null) === 'Sous 7 jours'
        && ($n['props']['color'] ?? null) === theme('accent'));
});

it('shows the alert timestamp on each recent-alert row', function () {
    // Mock (line 110): {{a.time}} — the recent-alert card has a third
    // line under the title/message. Mirrors alerts.blade.php's own
    // d/m/Y H:i formatting of created_at for consistency across screens.
    fakeDashboardEndpoints(alerts: [
        ['id' => 1, 'severity' => 'critical', 'title' => 'Anomalie détectée', 'message' => 'Ventes en baisse de 34%.', 'created_at' => '2026-08-14T10:00:00+00:00'],
    ]);

    Native::visit('/dashboard')->assertSee(Carbon::parse('2026-08-14T10:00:00+00:00')->format('d/m/Y H:i'));
});

it('looks up VIP and at-risk counts by segment name, not array position', function () {
    // Segments returned out of the "expected" order — proves the lookup is by
    // name, not index, since a naive $segments[0]/$segments[1] would swap these.
    fakeDashboardEndpoints(segments: [
        ['name' => 'at_risk', 'customers_count' => 9],
        ['name' => 'vip', 'customers_count' => 200],
    ]);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->vipCount())->toBe(200);
    expect($screen->instance()->atRiskCount())->toBe(9);
});

it('defaults VIP and at-risk counts to zero when the segment is absent', function () {
    fakeDashboardEndpoints(segments: []);

    $screen = Native::test(Dashboard::class);

    expect($screen->instance()->vipCount())->toBe(0);
    expect($screen->instance()->atRiskCount())->toBe(0);
});

it('shows the 3 most recent alerts with an empty state when there are none', function () {
    fakeDashboardEndpoints(alerts: []);

    Native::visit('/dashboard')->assertSee('Aucune alerte récente.');

    // Wire-level backstop for the /alerts request's per_page=3 cap — mirrors
    // this file's own convention of asserting /dashboard/widgets's `day` param
    // via Http::assertSent(...). Without this, a future edit that drops or
    // renames the param would silently make Accueil (the app's default
    // landing screen) render the server's full alert list with nothing
    // failing, since dashboard.blade.php has no client-side take(3) backstop.
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts')
        && ($request['per_page'] ?? null) === 3);
});

it('navigates to the alerts tab from "Tout voir"', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('goAlerts');

    $screen->assertNavigatedTo('/alerts');
});

it('wires the "Tout voir" pressable to goAlerts', function () {
    // Element::toArray() registers `@press`'s pressMethod as a top-level
    // `on_press` field regardless of whether the element type actually
    // reads it (the same trap documented for the now-deleted chart-bar
    // `@press`/selectBar tests) — so this only proves something if the id
    // is compared against the exact registered callback, not just isset().
    // Neither this file's other goAlerts() tests nor the header bell's own
    // test ever touch this specific element, so a broken/misspelled @press
    // binding on dashboard-alerts-see-all would otherwise go undetected.
    fakeDashboardEndpoints();

    Native::visit('/dashboard')
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'dashboard-alerts-see-all'
            && ($n['on_press'] ?? null) === callbackIdFor('goAlerts'));
});

it('shows a generic error instead of crashing when the forecasts endpoint returns a 500', function () {
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/dashboard/metrics*' => Http::response(['metrics' => ['revenue_today' => 320.5, 'orders_today' => 4, 'revenue_period' => 8450.0, 'orders_period' => 96, 'avg_order_value' => 88.02, 'products_count' => 214, 'low_stock_count' => 6, 'customers_count' => 312]], 200),
        '*/dashboard/widgets*' => Http::response(['widgets' => ['day' => 'today', 'date' => today()->toDateString(), 'ca_forecast_percent' => 62.5, 'ca_forecast_predicted' => 800.0, 'avg_cart' => 45.0, 'avg_cart_comparison' => 40.0, 'avg_cart_change' => 12.5, 'new_customers' => 3, 'week_revenue' => 1200.0, 'week_trend' => 8.0]], 200),
        '*/forecasts*' => Http::response(['message' => 'Server Error'], 500),
        '*/stock-depletion*' => Http::response(['stats' => ['total_products' => 0, 'out_of_stock' => 0, 'critical' => 0, 'avg_days_until_stockout' => 0, 'total_stock_value' => 0], 'products' => []], 200),
        '*/segments/stats*' => Http::response(['segments' => []], 200),
        '*/alerts*' => Http::response(['alerts' => []], 200),
        '*/shops*' => Http::response(['shops' => []], 200),
    ]);

    Native::visit('/dashboard')->assertSee('Server Error');
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

    // The sheet element is always present in the tree (the partial renders
    // unconditionally); only its `visible` prop tracks $shopSheetOpen. So
    // this asserts the sheet starts CLOSED, before openShopSwitcher() below.
    expect(findNodeByRef($screen->tree(), 'shop-switcher-sheet'))->not->toBeNull();
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

    // Each shop row carries its own logo image node, per the mockup
    // (line ~455) — same config('lumexio.asset_url') pattern as the header
    // shop pill and Login::logoUrl().
    expect(findNodeByRef($tree, 'shop-switcher-row-shop-1-logo'))->not->toBeNull();
    expect(findNodeByRef($tree, 'shop-switcher-row-shop-2-logo'))->not->toBeNull();

    // Mock (line 464): a presentational "+ Ajouter une boutique" affordance
    // below the shop list — no onClick in the mock either, so no wired
    // action is required here for parity.
    expect(findNodeByRef($tree, 'shop-switcher-add-shop'))->not->toBeNull();
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

it('no longer shows Stock, Commandes, or Fournisseurs in the account sheet', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('openAccountSheet');

    $tree = $screen->tree();
    expect(findNodeByRef($tree, 'account-sheet-stock'))->toBeNull();
    expect(findNodeByRef($tree, 'account-sheet-orders'))->toBeNull();
    expect(findNodeByRef($tree, 'account-sheet-suppliers'))->toBeNull();
});

it('navigates to the profile screen from the account sheet', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('goProfile');

    $screen->assertNavigatedTo('/profile');
});

it('navigates to the account screen from the account sheet', function () {
    fakeDashboardEndpoints();

    $screen = Native::test(Dashboard::class);
    $screen->call('goAccount');

    $screen->assertNavigatedTo('/account');
});

it('caches the active shop name in LocalState on refresh', function () {
    LocalState::current()->update(['shop_id' => 'shop-1']);
    fakeDashboardEndpoints(shops: [
        ['id' => 'shop-1', 'name' => 'Boutique Principale', 'sync_status' => 'idle', 'sync_error' => null],
    ]);

    Native::test(Dashboard::class);

    expect(LocalState::current()->fresh()->active_shop_name)->toBe('Boutique Principale');
});
