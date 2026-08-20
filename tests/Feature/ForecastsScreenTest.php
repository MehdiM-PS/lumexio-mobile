<?php

use App\NativeComponents\Screens\Forecasts;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Testing\Native;

// Fakes '*/auth/me*' too (unlike the retired ForecastSectionTest's version
// of this helper): Forecasts::refresh() calls loadCurrentUser() — a call
// the old embedded ForecastSection never made (HasHeaderChrome is new to
// this screen). Left unfaked, /auth/me falls through unmatched and throws,
// which callApi() catches and writes into $lastApiError — silently
// clobbering whatever the /forecasts stub below produced and making the
// "shows a generic error instead of crashing on failure" test pass for the
// wrong reason (an ambient /auth/me failure, not the /forecasts 500 it's
// meant to exercise). Faked in the $error branch too, so that branch's 500
// stays isolated to /forecasts.
//
// Also fakes the two stock-section endpoints (load() now calls
// loadStockCategories() + loadStock() right after the /forecasts fetch) —
// added for Task 6. '*/products?*per_page=20*' is deliberately narrower
// than a blanket '*/products*' pattern: loadStock() always sends
// per_page=20 (a fixed literal, unlike `sort`, which is *omitted* — not
// just defaulted — whenever the active stockSort isn't server-sortable,
// e.g. while avg_sales is the active sort; per_page=20 is therefore the
// one query fragment every loadStock() request carries unconditionally).
// The pre-existing per-product forecast search (updatedProductSearch(),
// GET /products?search=...&per_page=10) always sends per_page=10 instead,
// so this pattern never matches it — a test's own later, more specific
// '*/products?search=*' Http::fake() override (several already exist
// below) is therefore never shadowed by this default, since Http::fake()
// stubs are matched in registration order (first match wins) and this
// default flatly doesn't match that URL in the first place.
function fakeForecastsEndpoint(
    array $historical = [],
    array $forecasts = [],
    ?array $summary = null,
    ?string $error = null,
    array $stockProducts = [],
    array $stockCategories = [],
    ?array $stockPagination = null,
): void {
    $stockStubs = [
        '*/products/categories*' => Http::response(['categories' => $stockCategories], 200),
        '*/products?*per_page=20*' => Http::response([
            'products' => $stockProducts,
            'pagination' => $stockPagination ?? ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => count($stockProducts)],
        ], 200),
    ];

    if ($error !== null) {
        Http::fake([
            '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
            '*/forecasts*' => Http::response(['message' => $error], 500),
            ...$stockStubs,
        ]);

        return;
    }

    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/forecasts*' => Http::response([
            'forecasts' => $forecasts,
            'historical' => $historical,
            'summary' => $summary ?? ['forecast_7d' => 0, 'forecast_30d' => 0, 'historical_7d' => 0, 'historical_30d' => 0, 'trend' => 'stable', 'confidence' => 0],
            'top_product_forecasts' => [],
            'upcoming_events' => [],
        ], 200),
        ...$stockStubs,
    ]);
}

function sampleHistoricalDay(array $overrides = []): array
{
    return array_merge(['date' => today()->format('Y-m-d'), 'revenue' => 100.0, 'orders' => 3], $overrides);
}

function sampleForecastDay(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'forecast_date' => today()->addDay()->format('Y-m-d'),
        'predicted_quantity' => 5,
        'predicted_revenue' => 120.0,
        'confidence_score' => 78,
        'forecast_type' => 'daily',
        'factors' => [],
        'product' => null,
    ], $overrides);
}

function sampleStockProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'T-shirt',
        'reference' => 'TS-1',
        'quantity' => 12,
        'average_monthly_sales' => 3.2,
        'demand_30d' => 5,
        'days_until_stockout' => 40,
    ], $overrides);
}

it('fetches the shop-level forecast and historical data on mount', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay()], forecasts: [sampleForecastDay()], summary: ['forecast_7d' => 500.0, 'forecast_30d' => 2000.0, 'historical_7d' => 400.0, 'historical_30d' => 1800.0, 'trend' => 'up', 'confidence' => 82]);

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence' && str_contains($n['props']['text'] ?? '', '82'));

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/forecasts') && ! isset($request['product_id']));
});

// The chart is a single `lumexio_line_chart` wire node (Lumexio\NativeLineChart
// plugin — see packages/lumexio/native-line-chart) carrying the whole series
// as a JSON-encoded `data` prop, plus resolved theme hex colors: the
// historical-color prop must resolve the primary token (#2D5D5A,
// config/native-ui.php) and forecast-color the accent token (#EC7C0E). The
// forecast array repeats the last historical value at the join index (see
// chartData()) so the native renderer's two curves visually connect instead
// of leaving a gap.
it('renders the line chart with theme colors and a null-padded, joined historical/forecast series', function () {
    fakeForecastsEndpoint(
        historical: [sampleHistoricalDay(['date' => today()->format('Y-m-d'), 'revenue' => 100.0])],
        forecasts: [sampleForecastDay(['forecast_date' => today()->addDay()->format('Y-m-d'), 'predicted_revenue' => 120.0])],
    );

    $tree = Native::test(Forecasts::class)->tree();

    $chart = findNodeByRef($tree, 'forecast-chart');

    expect($chart)->not->toBeNull();
    expect($chart['type'])->toBe('lumexio_line_chart');
    expect($chart['props']['historical_color'] ?? null)->toBe('#2D5D5A');
    expect($chart['props']['forecast_color'] ?? null)->toBe('#EC7C0E');

    // json_encode() drops the trailing .0 on whole-number floats, so
    // json_decode() hands these back as plain ints — not a data problem
    // (native's JSONArray/JSONSerialization parse either shape as a number).
    $data = json_decode($chart['props']['data'] ?? '{}', true);
    expect($data['historical'])->toBe([100, null]);
    expect($data['forecast'])->toBe([100, 120]);
});

// `on_change` is registered against the bare `selectBar` method (unlike the
// old per-bar `<rect @press="selectBar({{ $index }})">`, which baked the
// index into the expression) — the native renderer reads the tapped point's
// index and sends it as the callback's runtime argument via the same
// TAB_CHANGE wire event native:tab-row uses. See
// packages/lumexio/native-line-chart's LineChartRenderer (Kotlin/Swift).
it('selects a chart point via the real binding and shows a Réalisé/Prévu readout', function () {
    // Dated yesterday, not today's sampleHistoricalDay() default — today
    // would already be preselected on load (see defaultSelectedBarIndex()),
    // and calling selectBar(0) below would then toggle it back OFF instead
    // of turning it on.
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['revenue' => 88.5, 'date' => today()->subDay()->format('Y-m-d')])], forecasts: []);

    $screen = Native::test(Forecasts::class)
        ->assertElement('lumexio_line_chart', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-chart' && ($n['props']['on_change'] ?? null) === callbackIdFor('selectBar'));

    $screen->call('selectBar', 0);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-tooltip' && str_contains($n['props']['text'] ?? '', 'Réalisé'));
});

it('preselects today on load, showing its readout without any tap', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['revenue' => 88.5, 'date' => today()->format('Y-m-d')])], forecasts: []);

    Native::test(Forecasts::class)
        ->assertElement('lumexio_line_chart', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-chart' && ($n['props']['selected_index'] ?? null) === 0)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-tooltip' && str_contains($n['props']['text'] ?? '', 'Réalisé'));
});

it('preselects nothing when today is outside the charted window', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['date' => today()->subDays(2)->format('Y-m-d')])], forecasts: []);

    $screen = Native::test(Forecasts::class);

    $chart = findNodeByRef($screen->tree(), 'forecast-chart');
    expect($chart['props']['selected_index'] ?? null)->toBeNull();
    $screen->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-tooltip');
});

// The ref lives on the `pressable` row (needed by the selection test below
// to assert its on_press binding), not on the inner `text` node — confirmed
// by dumping the compiled tree, where the child text carries no `ref` at
// all. So this checks the product name via the pressable's children rather
// than asserting a `text` element by that ref (which doesn't exist).
it('searches products via the real debounced binding and shows results', function () {
    fakeForecastsEndpoint();
    Http::fake(['*/products?search=*' => Http::response(['products' => [['id' => 5, 'name' => 'T-shirt bleu', 'reference' => 'TS-BLUE']]], 200)]);

    $screen = Native::test(Forecasts::class)
        ->assertElement('outlined_text_input', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-product-search' && ($n['props']['on_change'] ?? null) === callbackIdFor("__syncProperty('productSearch')"));

    $screen->set('productSearch', 'bleu');

    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-product-result-5'
        && collect($n['children'] ?? [])->contains(fn (array $c): bool => ($c['props']['text'] ?? null) === 'T-shirt bleu'));
});

// `<pressable @press>` rows, like `<rect @press>` above, land at the wire
// node's TOP LEVEL (on_press) — confirmed against
// SupplierOrdersTabTest's `so-xyz-789` row assertion.
it('selects a product via the real binding, refetches, and shows a clear button', function () {
    fakeForecastsEndpoint(); // covers both the initial shop-level fetch and the product-scoped refetch (same pattern)
    Http::fake(['*/products?search=*' => Http::response(['products' => [['id' => 5, 'name' => 'T-shirt bleu', 'reference' => 'TS-BLUE']]], 200)]);

    $screen = Native::test(Forecasts::class);
    $screen->set('productSearch', 'bleu');
    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-product-result-5' && ($n['on_press'] ?? null) === callbackIdFor('selectProduct(5)'));

    $screen->call('selectProduct', 5);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/forecasts') && ($request['product_id'] ?? null) === 5);
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-selected-product' && str_contains($n['props']['text'] ?? '', 'T-shirt bleu'))
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-clear-product' && ($n['props']['on_press'] ?? null) === callbackIdFor('clearProduct'));
});

// Regression: `selectProduct()` used to take a second baked string argument
// (the product name), interpolated raw into `@press="selectProduct(id,
// 'name')"`. NativeTagPrecompiler::compileAttributeValue() deliberately
// skips HTML-escaping `@press` attributes, so a name containing an
// apostrophe (e.g. "L'Oréal") produced a malformed expression;
// CallbackRegistry::parse() then failed to decode the arguments and fell
// back to an empty arg list, so NativeComponent::dispatch() called
// selectProduct() with zero arguments against a two-required-param
// signature — an uncaught ArgumentCountError (500 crash on tap). The fix
// drops the baked name and looks it up from $productResults instead (same
// pattern as SuppliersTab::select()/SupplierOrdersTab::select()). This test
// calls $screen->call('selectProduct', 9) directly, bypassing blade
// compilation and CallbackRegistry::parse() — it does NOT trace the fix
// end-to-end through the compiled `@press` binding. What it proves is
// structural: selectProduct()'s signature is a single scalar int (no baked
// name argument), so the compiled `@press="selectProduct({{ $product['id']
// }})"` binding can never contain a raw string with an unescaped apostrophe
// in the first place — the vulnerable code path is unreachable by
// construction, not merely unexercised. The apostrophe-bearing name here
// only confirms the lookup-by-id still renders it correctly.
it('selects a product whose name contains an apostrophe without crashing', function () {
    fakeForecastsEndpoint();
    Http::fake(['*/products?search=*' => Http::response(['products' => [['id' => 9, 'name' => "L'Oréal", 'reference' => 'LO-001']]], 200)]);

    $screen = Native::test(Forecasts::class);
    $screen->set('productSearch', 'oreal');
    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-product-result-9' && ($n['on_press'] ?? null) === callbackIdFor('selectProduct(9)'));

    $screen->call('selectProduct', 9);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-selected-product' && ($n['props']['text'] ?? null) === "L'Oréal");
});

it('clears the selected product via the real binding and returns to the shop-level view', function () {
    fakeForecastsEndpoint();

    $screen = Native::test(Forecasts::class);
    $screen->call('selectProduct', 5);

    $screen->call('clearProduct');

    // forecast-confidence now renders in both the shop-level and
    // product-selected views (see headerConfidence()), so it no longer
    // distinguishes the two — assert the clear-product button is gone too,
    // which only ever renders when a product is selected, to confirm the
    // shop-level view genuinely re-rendered rather than just the product
    // header text vanishing.
    $screen->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-selected-product')
        ->assertMissingElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-clear-product')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence');
});

it("shows the selected product's confidence in the header immediately after selecting it, before any bar is tapped", function () {
    // Distinct, non-zero forecast confidence scores (mean = 82) vs. the
    // shop summary's confidence (0, from fakeForecastsEndpoint's default) —
    // proves the header reads the product's own forecasts, not the summary.
    fakeForecastsEndpoint(forecasts: [
        sampleForecastDay(['confidence_score' => 91]),
        sampleForecastDay(['confidence_score' => 73]),
    ]);
    Http::fake(['*/products?search=*' => Http::response(['products' => [['id' => 5, 'name' => 'T-shirt bleu', 'reference' => 'TS-BLUE']]], 200)]);

    $screen = Native::test(Forecasts::class);
    $screen->call('selectProduct', 5);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence' && str_contains($n['props']['text'] ?? '', '82'));
});

it('slices the chart to the last 14 historical days and first 7 forecast days when more data is available', function () {
    // 30 historical days (oldest to newest) and 10 forecast days.
    $historicalDays = collect(range(0, 29))
        ->map(fn (int $i) => sampleHistoricalDay(['date' => today()->subDays(29 - $i)->format('Y-m-d')]))
        ->all();

    $forecastDays = collect(range(1, 10))
        ->map(fn (int $i) => sampleForecastDay(['forecast_date' => today()->addDays($i)->format('Y-m-d')]))
        ->all();

    // Shuffled so chartSeries()'s sortBy() is actually load-bearing here —
    // fed in date order, the slice/take would pass even without sorting.
    shuffle($historicalDays);
    shuffle($forecastDays);

    fakeForecastsEndpoint(historical: $historicalDays, forecasts: $forecastDays);

    $tree = Native::test(Forecasts::class)->tree();

    $chart = findNodeByRef($tree, 'forecast-chart');
    $data = json_decode($chart['props']['data'] ?? '{}', true);

    // chartSeries() slices to the last 14 historical days + first 7 forecast
    // days = 21 points total, regardless of how much data the API returned.
    expect($data['dates'])->toHaveCount(21);
    expect($data['historical'])->toHaveCount(21);
    expect($data['forecast'])->toHaveCount(21);

    // slice(-14) on the 30 sorted historical days keeps indexes 16..29, so
    // index 0 of the combined series is historical[30-14] = historical[16]
    // (the 17th of the 30 days).
    $expectedFirstDate = today()->subDays(29 - 16)->format('Y-m-d');

    // take(7) on the sorted forecasts keeps the first 7, so index 20 of the
    // combined series (14 historical + the 7th forecast day) is forecasts[6].
    $expectedLastDate = today()->addDays(7)->format('Y-m-d');

    expect($data['dates'][0])->toBe($expectedFirstDate);
    expect($data['dates'][20])->toBe($expectedLastDate);
});

it('shows a generic error instead of crashing on failure', function () {
    fakeForecastsEndpoint(error: 'boom');

    Native::test(Forecasts::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-error');
});

// Deliberately not two separate fakeForecastsEndpoint() calls: Http::fake()
// stubs are matched in registration order (first matching pattern wins, per
// PendingRequest::buildStubHandler()'s ->filter()->first()), so a second
// fakeForecastsEndpoint() call for the same '*/forecasts*' pattern would
// never actually take effect for the refresh() call below — same trap as
// the "opens and closes the shop switcher sheet" comment in
// DashboardScreenTest, just hit here because both calls target the exact
// same URL rather than a broader-then-narrower pair. A response sequence
// (ItemDetailScreenTest's "refetches stock history on pull-to-refresh"
// pattern) queues one response per request instead.
it('is wrapped in a refreshable that calls refresh on pull-to-refresh', function () {
    $baseResponse = [
        'summary' => ['forecast_7d' => 0, 'forecast_30d' => 0, 'historical_7d' => 0, 'historical_30d' => 0, 'trend' => 'stable', 'confidence' => 0],
        'top_product_forecasts' => [],
        'upcoming_events' => [],
    ];

    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/forecasts*' => Http::sequence()
            ->push([...$baseResponse, 'historical' => [sampleHistoricalDay()], 'forecasts' => [sampleForecastDay()]], 200)
            ->push([...$baseResponse, 'historical' => [sampleHistoricalDay(['date' => today()->addDay()->format('Y-m-d')])], 'forecasts' => [sampleForecastDay()]], 200),
        '*/products/categories*' => Http::response(['categories' => []], 200),
        '*/products?*per_page=20*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]], 200),
    ]);

    $screen = Native::test(Forecasts::class);
    $tree = $screen->tree();

    expect($tree['type'] ?? null)->toBe('refreshable');
    // Proves @refresh="refresh" is bound to THIS method specifically —
    // isset() alone would still pass for a typo like "refres" (see
    // ItemDetailScreenTest's "wraps its content in a refreshable" test).
    // refresh() always busts the short-lived GET cache before reloading, so
    // pull-to-refresh actually re-hits the network.
    expect($tree['props']['on_refresh'] ?? null)->toBe(callbackIdFor('refresh'));

    $screen->call('refresh');

    expect($screen->get('historical')[0]['date'])->toBe(today()->addDay()->format('Y-m-d'));
});

it('opens the shop switcher sheet from the shared header trait', function () {
    fakeForecastsEndpoint(historical: [], forecasts: []);
    Http::fake(['*/api/v1/shops' => Http::response(['shops' => []])]);

    $screen = Native::test(Forecasts::class);
    $screen->call('openShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeTrue();
});

// Proves the /forecasts route registration + TabsLayout wiring, not just
// the screen class in isolation — Native::visit() (unlike Native::test())
// resolves the layout from the route itself, so this is the only test in
// this file that would fail if routes/web.php's registration were missing
// (matches StockScreenTest's "shows the shared tab bar" convention).
it('is reachable at /forecasts with the Prévisions tab active', function () {
    fakeForecastsEndpoint(historical: [], forecasts: []);

    Native::visit('/forecasts')
        ->assertHasTab('Prévisions')
        ->assertTabActive('Prévisions');
});

// ── Stock & réapprovisionnement (Task 6) ────────────────────────────────

it('loads stock items and categories on mount alongside the forecast data', function () {
    fakeForecastsEndpoint(
        historical: [sampleHistoricalDay()],
        forecasts: [sampleForecastDay()],
        stockProducts: [sampleStockProduct()],
        stockCategories: [['id' => 1, 'name' => 'Vêtements']],
    );

    $screen = Native::test(Forecasts::class);

    expect($screen->get('stockItems'))->toHaveCount(1);
    expect($screen->get('stockCategories'))->toHaveCount(1);
});

it('defaults to hiding inactive products and not hiding out-of-stock ones', function () {
    fakeForecastsEndpoint();

    $screen = Native::test(Forecasts::class);

    expect($screen->get('stockHideInactive'))->toBeTrue();
    expect($screen->get('stockHideOOS'))->toBeFalse();
});

it('binds the stock search input to the real debounced syncProperty callback', function () {
    fakeForecastsEndpoint();

    Native::test(Forecasts::class)
        ->assertElement('outlined_text_input', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-search-input'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("__syncProperty('stockSearch')"));
});

it('searches stock via the real debounced binding, resets to page 1, and sends the search param', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['name' => 'T-shirt bleu'])]);

    $screen = Native::test(Forecasts::class);
    $screen->set('stockPage', 3); // simulate having paginated before searching
    $screen->set('stockSearch', 'bleu');

    expect($screen->get('stockPage'))->toBe(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['search'] ?? null) === 'bleu');
});

it('wires both visibility toggles to the real syncProperty binding', function () {
    fakeForecastsEndpoint();

    Native::test(Forecasts::class)
        ->assertElement('toggle', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-toggle-hide-inactive'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("__syncProperty('stockHideInactive')"))
        ->assertElement('toggle', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-toggle-hide-oos'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("__syncProperty('stockHideOOS')"));
});

it('keeps the hide-inactive toggle stable when it receives a redundant echo of its current value', function () {
    // Regression test: the toggle used to be wired with a blind
    // negate-on-change handler (:value + @change="toggleStockHideInactive"
    // flipping the current value on every event). iOS's toggle renderer can
    // fire a spurious on_change echo carrying the *already-current* value
    // (e.g. reconciling its local @State against the server value on
    // mount) — with the old blind-negate handler that echo flipped the
    // value and produced a self-perpetuating on/off loop. native:model's
    // syncProperty binding assigns whatever value it receives instead of
    // negating, so an echo of the current value is a no-op.
    fakeForecastsEndpoint();

    $screen = Native::test(Forecasts::class);
    expect($screen->get('stockHideInactive'))->toBeTrue();

    $screen->toggle('stock-toggle-hide-inactive', true);

    expect($screen->get('stockHideInactive'))->toBeTrue();
});

it('sends include_inactive=true when the hide-inactive toggle is switched off via the real binding', function () {
    fakeForecastsEndpoint();

    Native::test(Forecasts::class)->toggle('stock-toggle-hide-inactive', false);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['include_inactive'] ?? null) === true);
});

it('sends exclude_out_of_stock=true when the hide-out-of-stock toggle is switched on via the real binding', function () {
    fakeForecastsEndpoint();

    Native::test(Forecasts::class)->toggle('stock-toggle-hide-oos', true);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['exclude_out_of_stock'] ?? null) === true);
});

it('opens and closes the category dropdown, and lets a category be selected via the real binding', function () {
    fakeForecastsEndpoint(stockCategories: [['id' => 1, 'name' => 'Vêtements'], ['id' => 2, 'name' => 'Chaussures']]);

    $screen = Native::test(Forecasts::class)
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-category-dropdown-toggle'
            && ($n['on_press'] ?? null) === callbackIdFor('toggleStockCategoryDropdown'));

    $screen->call('toggleStockCategoryDropdown');
    expect($screen->get('stockCatOpen'))->toBeTrue();

    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-category-option-2'
        && ($n['on_press'] ?? null) === callbackIdFor('selectStockCategory(2)'));

    $screen->call('selectStockCategory', 2);

    expect($screen->get('stockCategoryId'))->toBe(2);
    expect($screen->get('stockCatOpen'))->toBeFalse();
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['category_ids'] ?? null) === [2]);
});

it("filters the category dropdown's options via its own client-side search field", function () {
    fakeForecastsEndpoint(stockCategories: [['id' => 1, 'name' => 'Vêtements'], ['id' => 2, 'name' => 'Chaussures']]);

    $screen = Native::test(Forecasts::class);
    $screen->call('toggleStockCategoryDropdown');
    $screen->set('stockCategorySearch', 'chau');

    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-category-option-2')
        ->assertMissingElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-category-option-1');
});

it('resets the category search field when the dropdown is reopened', function () {
    fakeForecastsEndpoint(stockCategories: [['id' => 1, 'name' => 'Vêtements']]);

    $screen = Native::test(Forecasts::class);
    $screen->call('toggleStockCategoryDropdown');
    $screen->set('stockCategorySearch', 'vet');
    $screen->call('toggleStockCategoryDropdown'); // close
    $screen->call('toggleStockCategoryDropdown'); // reopen

    expect($screen->get('stockCategorySearch'))->toBe('');
});

it('sends server-side sort + dir for the name, stock, and days_left columns', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct()]);

    $screen = Native::test(Forecasts::class);

    $screen->call('setStockSort', 'stock');
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['sort'] ?? null) === 'stock' && ($request['dir'] ?? null) === 'asc');

    // Tapping the same column again flips the direction.
    $screen->call('setStockSort', 'stock');
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['sort'] ?? null) === 'stock' && ($request['dir'] ?? null) === 'desc');

    $screen->call('setStockSort', 'days_left');
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['sort'] ?? null) === 'days_left');
});

// Regression: the /products endpoint (Task 1) only accepts
// sort=name|stock|days_left — sending sort=avg_sales 422s. avg_sales must
// stay client-side-only: never sent as the `sort` param, and applied via a
// local usort() on the already-loaded page instead.
it('never sends sort=avg_sales to the API and reorders the loaded page client-side instead', function () {
    fakeForecastsEndpoint(stockProducts: [
        sampleStockProduct(['id' => 1, 'name' => 'A', 'average_monthly_sales' => 5.0]),
        sampleStockProduct(['id' => 2, 'name' => 'B', 'average_monthly_sales' => 1.0]),
        sampleStockProduct(['id' => 3, 'name' => 'C', 'average_monthly_sales' => 9.0]),
    ]);

    $screen = Native::test(Forecasts::class);
    $screen->call('setStockSort', 'avg_sales');

    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['sort'] ?? null) === 'avg_sales');

    expect(array_column($screen->get('stockItems'), 'id'))->toBe([2, 1, 3]); // ascending by average_monthly_sales

    // Toggling again flips direction and re-sorts without hitting the network again.
    Http::fake(); // reset the recorder so assertSentCount below only covers this action
    $screen->call('setStockSort', 'avg_sales');
    expect(array_column($screen->get('stockItems'), 'id'))->toBe([3, 1, 2]); // descending
    Http::assertNothingSent();
});

// Regression for the actual bug: setStockSort('avg_sales') itself never hits
// the network (the test above), so it can't be the thing that would have
// 422'd against the plan's original draft. The real bug lives in the *next*
// reload while avg_sales is still the active sort — pagination, a toggle, or
// a search all call loadStock() again, and the draft's loadStock() would
// have sent the stale `sort=avg_sales` value straight through, which
// GET /products rejects (only name|stock|days_left are valid server-side,
// per lumexio-web-app's ProductsController::index()). This drives a real
// reload (stockPageNext()) with avg_sales active and asserts both that no
// `sort` param referencing avg_sales is sent, and that the freshly-reloaded
// page comes back re-sorted by average_monthly_sales — proving
// sortStockItemsByAvgSales() is re-applied after every reload, not just on
// the original setStockSort() call.
it('keeps avg_sales sorting client-side across a reload triggered while it is the active sort', function () {
    $unsorted = [
        sampleStockProduct(['id' => 1, 'name' => 'A', 'average_monthly_sales' => 5.0]),
        sampleStockProduct(['id' => 2, 'name' => 'B', 'average_monthly_sales' => 1.0]),
        sampleStockProduct(['id' => 3, 'name' => 'C', 'average_monthly_sales' => 9.0]),
    ];

    fakeForecastsEndpoint(
        stockProducts: $unsorted,
        stockPagination: ['current_page' => 1, 'last_page' => 2, 'per_page' => 20, 'total' => 6],
    );

    $screen = Native::test(Forecasts::class);
    $screen->call('setStockSort', 'avg_sales');
    expect(array_column($screen->get('stockItems'), 'id'))->toBe([2, 1, 3]); // ascending, set locally, no request yet

    // Trigger a real reload while avg_sales is still active. The stub
    // returns the same unsorted fixture regardless of page/params — what
    // matters is (a) what was sent, and (b) that the freshly-assigned page
    // is re-sorted rather than left in the stub's raw (unsorted) order.
    $screen->call('stockPageNext');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['page'] ?? null) === 2
        && ! isset($request['sort'])); // omitted entirely, not merely != 'avg_sales'

    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/products?')
        && ($request['sort'] ?? null) === 'avg_sales');

    // Still ascending by average_monthly_sales after the reload — if
    // sortStockItemsByAvgSales() weren't re-applied post-reload, this would
    // be [1, 2, 3] (the stub's raw, unsorted order) instead.
    expect(array_column($screen->get('stockItems'), 'id'))->toBe([2, 1, 3]);
});

it('paginates stock results, disabling prev on the first page and next on the last', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct()], stockPagination: ['current_page' => 1, 'last_page' => 3, 'per_page' => 20, 'total' => 50]);

    $screen = Native::test(Forecasts::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-prev' && ($n['props']['on_press'] ?? null) === callbackIdFor('stockPagePrev'))
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-next' && ($n['props']['on_press'] ?? null) === callbackIdFor('stockPageNext'));

    expect($screen->get('stockPage'))->toBe(1);

    $screen->call('stockPagePrev'); // no-op on page 1
    expect($screen->get('stockPage'))->toBe(1);

    $screen->call('stockPageNext');
    expect($screen->get('stockPage'))->toBe(2);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?') && ($request['page'] ?? null) === 2);
});

it('navigates to the item detail screen when a stock row is tapped', function () {
    // Sparse fixture (no reference/quantity/average_monthly_sales/days_until_stockout)
    // — proves the row and the tap survive a partial API payload.
    fakeForecastsEndpoint(stockProducts: [['id' => 7, 'name' => 'T-shirt']]);

    $screen = Native::test(Forecasts::class)
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-row-7' && ($n['on_press'] ?? null) === callbackIdFor('selectStockItem(7)'));

    $screen->call('selectStockItem', 7);

    $screen->assertNavigatedTo('/stock/item/product/7');
});

it('shows an empty-state message when no stock items match the filters', function () {
    fakeForecastsEndpoint(stockProducts: []);

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-empty');
});

// Regression: stockPagePrev()/stockPageNext() used to leave $stockPage
// incremented/decremented even when the resulting loadStock() call failed,
// showing an incremented page number over an empty table (the reload's
// failure clears stockItems, same convention as the failed-forecasts test
// above). Both actions must roll $stockPage back to its pre-request value
// whenever the reload leaves $lastApiError set.
//
// Deliberately not fakeForecastsEndpoint() + a second Http::fake() override
// for the failing call: Http::fake() stubs are matched in registration
// order (first match wins), so a later, equally-broad '*/products?*per_page=20*'
// stub never actually overrides the fakeForecastsEndpoint() one already
// registered — same trap documented on the "wrapped in a refreshable" test
// above. A response sequence queues one response per request instead.
function fakeForecastsWithProductsSequence(ResponseSequence $products): void
{
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/forecasts*' => Http::response([
            'forecasts' => [], 'historical' => [],
            'summary' => ['forecast_7d' => 0, 'forecast_30d' => 0, 'historical_7d' => 0, 'historical_30d' => 0, 'trend' => 'stable', 'confidence' => 0],
            'top_product_forecasts' => [], 'upcoming_events' => [],
        ], 200),
        '*/products/categories*' => Http::response(['categories' => []], 200),
        '*/products?*per_page=20*' => $products,
    ]);
}

it('rolls stockPage back when stockPageNext triggers a failing reload', function () {
    fakeForecastsWithProductsSequence(
        Http::sequence()
            ->push(['products' => [sampleStockProduct()], 'pagination' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 20, 'total' => 50]], 200)
            ->push(['message' => 'boom'], 500)
    );

    $screen = Native::test(Forecasts::class);
    expect($screen->get('stockPage'))->toBe(1);

    $screen->call('stockPageNext');

    expect($screen->get('stockPage'))->toBe(1);
    expect($screen->get('lastApiError'))->not->toBeNull();
});

it('rolls stockPage back when stockPagePrev triggers a failing reload', function () {
    fakeForecastsWithProductsSequence(
        Http::sequence()
            ->push(['products' => [sampleStockProduct()], 'pagination' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 20, 'total' => 50]], 200)
            ->push(['message' => 'boom'], 500)
    );

    $screen = Native::test(Forecasts::class);
    // Jumps to page 3 without a network call (no updatedStockPage() hook —
    // same "simulate having paginated before" trick the search test above
    // uses) so stockPagePrev()'s request below (page 2) is a fresh query,
    // not a repeat of the already-cached page-1 request from mount — a
    // repeat would hit the short-lived GET cache instead of the second,
    // failing sequence entry (loadStock() never calls bustApiCache()).
    $screen->set('stockPage', 3);

    $screen->call('stockPagePrev'); // attempts page 2, fails

    expect($screen->get('stockPage'))->toBe(3);
    expect($screen->get('lastApiError'))->not->toBeNull();
});

// A failed reload must not also strand the Prev/Next buttons and the
// page-range label — stockLastPage/stockTotal/stockPerPage should keep
// their pre-failure values rather than resetting to the empty-response
// defaults (last_page=1, total=0), which combined with the $stockPage
// rollback above would otherwise disable Next and show "0 sur 0".
it('keeps pagination metadata intact after a failed reload, alongside the stockPage rollback', function () {
    fakeForecastsWithProductsSequence(
        Http::sequence()
            ->push(['products' => [sampleStockProduct()], 'pagination' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 20, 'total' => 50]], 200)
            ->push(['message' => 'boom'], 500)
    );

    $screen = Native::test(Forecasts::class);

    $screen->call('stockPageNext');

    expect($screen->get('stockLastPage'))->toBe(3);
    expect($screen->get('stockTotal'))->toBe(50);
    $screen->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-next' && ($n['props']['disabled'] ?? false) === false);
});

// ── Task 7 gap-fill: pixel-audit fixes for the stock table (Demande 30j
// column, mockup wording, color-coded urgency, page-range pagination) ──

it('shows the demand_30d value on the stock card', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['demand_30d' => 17])]);

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-row-1-demand' && ($n['props']['text'] ?? null) === '17');
});

it("uses the mockup's exact wording for the average-sales and days-left sort pills", function () {
    fakeForecastsEndpoint();

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['props']['text'] ?? null) === 'Vente moy./mois')
        ->assertElement('text', fn (array $n): bool => ($n['props']['text'] ?? null) === 'Jrs avant rupture');
});

it('renders the Demande 30j label on each card as plain, non-sortable text with no sort pill of its own', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct()]);

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['props']['text'] ?? null) === 'Demande 30j' && ! isset($n['on_press']));

    expect(findNodeByRef(Native::test(Forecasts::class)->tree(), 'stock-sort-demand-30d'))->toBeNull();
});

it('color-codes the stock quantity destructive only when the product is out of stock', function () {
    fakeForecastsEndpoint(stockProducts: [
        sampleStockProduct(['id' => 1, 'quantity' => 0]),
        sampleStockProduct(['id' => 2, 'quantity' => 5]),
    ]);

    $tree = Native::test(Forecasts::class)->tree();

    $outOfStockCell = findNodeByRef($tree, 'stock-row-1-quantity');
    $inStockCell = findNodeByRef($tree, 'stock-row-2-quantity');

    expect($outOfStockCell['props']['color'] ?? null)->toBe('#E24947'); // theme destructive
    expect($inStockCell['props']['color'] ?? null)->toBe('#14151A'); // theme on-surface
});

it('shows "Rupture" and a destructive color in the days-left field when out of stock, even with a non-null day count', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['id' => 1, 'quantity' => 0, 'days_until_stockout' => 3])]);

    $daysCell = findNodeByRef(Native::test(Forecasts::class)->tree(), 'stock-row-1-days-left');

    expect($daysCell['props']['text'] ?? null)->toBe('Rupture');
    expect($daysCell['props']['color'] ?? null)->toBe('#E24947');
});

it('color-codes the days-left field destructive when 7 days or fewer remain', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['id' => 1, 'quantity' => 5, 'days_until_stockout' => 5])]);

    $daysCell = findNodeByRef(Native::test(Forecasts::class)->tree(), 'stock-row-1-days-left');

    expect($daysCell['props']['text'] ?? null)->toBe('5 j');
    expect($daysCell['props']['color'] ?? null)->toBe('#E24947');
});

it('color-codes the days-left field accent (warning) when 21 days or fewer, but more than 7, remain', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['id' => 1, 'quantity' => 5, 'days_until_stockout' => 15])]);

    $daysCell = findNodeByRef(Native::test(Forecasts::class)->tree(), 'stock-row-1-days-left');

    expect($daysCell['props']['text'] ?? null)->toBe('15 j');
    expect($daysCell['props']['color'] ?? null)->toBe('#EC7C0E'); // theme accent
});

// Regression: a naive `days_until_stockout <= 7` check without a null guard
// is true in PHP for null (loosely compares as 0), which would wrongly
// destructive-color a product with an unknown days-left value.
it('shows a neutral color and an em dash in the days-left field when the day count is unknown', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct(['id' => 1, 'quantity' => 5, 'days_until_stockout' => null])]);

    $daysCell = findNodeByRef(Native::test(Forecasts::class)->tree(), 'stock-row-1-days-left');

    expect($daysCell['props']['text'] ?? null)->toBe('—');
    expect($daysCell['props']['color'] ?? null)->toBe('#14151A'); // theme on-surface
});

it('shows the active sort pill highlighted with a direction arrow, and flips direction on repeat tap', function () {
    fakeForecastsEndpoint();

    $screen = Native::test(Forecasts::class);
    $tree = $screen->tree();

    $nameSort = findNodeByRef($tree, 'stock-sort-name');
    expect($nameSort['children'][0]['props']['text'] ?? null)->toBe('Nom ↑');
    expect($nameSort['style']['bg_color'] ?? null)->toContain('2D5D5A'); // theme primary — active

    $stockSort = findNodeByRef($tree, 'stock-sort-stock');
    expect($stockSort['children'][0]['props']['text'] ?? null)->toBe('Stock');
    expect($stockSort['style']['bg_color'] ?? null)->toContain('F0EDE8'); // theme surface-variant — inactive

    $screen->call('setStockSort', 'name');
    expect($screen->get('stockDir'))->toBe('desc');
});

it('shows a page-range summary ("start–end sur total") instead of "Page N / M"', function () {
    fakeForecastsEndpoint(
        stockProducts: [sampleStockProduct(['id' => 1]), sampleStockProduct(['id' => 2])],
        stockPagination: ['current_page' => 1, 'last_page' => 5, 'per_page' => 2, 'total' => 10],
    );

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-range' && ($n['props']['text'] ?? null) === '1–2 sur 10');
});

it('advances the page-range summary to reflect the new page after paginating', function () {
    fakeForecastsEndpoint(
        stockProducts: [sampleStockProduct(['id' => 1]), sampleStockProduct(['id' => 2])],
        stockPagination: ['current_page' => 1, 'last_page' => 5, 'per_page' => 2, 'total' => 10],
    );

    $screen = Native::test(Forecasts::class);
    $screen->call('stockPageNext');

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-range' && ($n['props']['text'] ?? null) === '3–4 sur 10');
});

it('uses icon buttons (not text labels) for the pagination prev/next controls', function () {
    fakeForecastsEndpoint(stockProducts: [sampleStockProduct()], stockPagination: ['current_page' => 1, 'last_page' => 3, 'per_page' => 20, 'total' => 50]);

    Native::test(Forecasts::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-prev'
            && ($n['props']['leading_icon'] ?? null) === 'chevron.left'
            && ($n['props']['label'] ?? null) === null)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-page-next'
            && ($n['props']['leading_icon'] ?? null) === 'chevron.right'
            && ($n['props']['label'] ?? null) === null);
});

// ── Shop-switcher "+ Ajouter une boutique" (Task 7 gap-fill) ────────────

it('wires the "+ Ajouter une boutique" row to open the marketing site in the system browser', function () {
    fakeForecastsEndpoint();
    Http::fake(['*/api/v1/shops' => Http::response(['shops' => []])]);

    $screen = Native::test(Forecasts::class);
    $screen->call('openShopSwitcher');

    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'shop-switcher-add-shop' && ($n['on_press'] ?? null) === callbackIdFor('openAddShop'));

    Browser::shouldReceive('open')->once()->with(config('lumexio.asset_url'));
    $screen->call('openAddShop');
});
