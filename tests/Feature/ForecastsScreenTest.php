<?php

use App\NativeComponents\Screens\Forecasts;
use Illuminate\Support\Facades\Http;
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
function fakeForecastsEndpoint(array $historical = [], array $forecasts = [], ?array $summary = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake([
            '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
            '*/forecasts*' => Http::response(['message' => $error], 500),
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

it('fetches the shop-level forecast and historical data on mount', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay()], forecasts: [sampleForecastDay()], summary: ['forecast_7d' => 500.0, 'forecast_30d' => 2000.0, 'historical_7d' => 400.0, 'historical_30d' => 1800.0, 'trend' => 'up', 'confidence' => 82]);

    Native::test(Forecasts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence' && str_contains($n['props']['text'] ?? '', '82'));

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/forecasts') && ! isset($request['product_id']));
});

// Tailwind classes resolve into a `style.bg_color` hex on the wire node
// (confirmed by dumping the compiled tree for this exact scenario) — there
// is no literal `class` string left in `props` to str_contains() against,
// unlike text/font props. So this compares the actually-resolved colors:
// the historical bar must resolve the primary token (#2D5D5A, config/native-ui.php)
// and the forecast bar the accent token (#EC7C0E), proving the chart visually
// distinguishes historical-vs-forecast days rather than reusing one color.
it('renders a bar per historical and forecast day, with distinct colors for historical vs forecast', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['date' => today()->format('Y-m-d')])], forecasts: [sampleForecastDay(['forecast_date' => today()->addDay()->format('Y-m-d')])]);

    $tree = Native::test(Forecasts::class)->tree();

    $bar0 = findNodeByRef($tree, 'forecast-bar-0');
    $bar1 = findNodeByRef($tree, 'forecast-bar-1');

    expect($bar0)->not->toBeNull();
    expect($bar1)->not->toBeNull();
    expect($bar0['style']['bg_color'] ?? null)->toContain('2D5D5A');
    expect($bar1['style']['bg_color'] ?? null)->toContain('EC7C0E');
    expect($bar0['style']['bg_color'] ?? null)->not->toBe($bar1['style']['bg_color'] ?? null);
});

// `<rect @press>` bars compile with the callback id at the wire node's TOP
// LEVEL (on_press), the same as Dashboard's own chart bars — not nested
// under props like `<button @press>`. Confirmed against
// tests/Feature/DashboardScreenTest.php's `dashboard-chart-bar-0` assertion.
it('selects a bar via the real binding and shows a Réalisé/Prévu readout', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['revenue' => 88.5])], forecasts: []);

    $screen = Native::test(Forecasts::class)
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-bar-0' && ($n['on_press'] ?? null) === callbackIdFor('selectBar(0)'));

    $screen->call('selectBar', 0);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-tooltip' && str_contains($n['props']['text'] ?? '', 'Réalisé'));
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

    $screen = Native::test(Forecasts::class);
    $tree = $screen->tree();

    // chartSeries() slices to the last 14 historical days + first 7 forecast
    // days = 21 bars total, regardless of how much data the API returned.
    $barCount = collect(range(0, 40))->filter(fn (int $i) => findNodeByRef($tree, "forecast-bar-{$i}") !== null)->count();
    expect($barCount)->toBe(21);

    // slice(-14) on the 30 sorted historical days keeps indexes 16..29, so
    // index 0 of the combined series is historical[30-14] = historical[16]
    // (the 17th of the 30 days).
    $expectedFirstDate = today()->subDays(29 - 16)->format('Y-m-d');

    // take(7) on the sorted forecasts keeps the first 7, so index 20 of the
    // combined series (14 historical + the 7th forecast day) is forecasts[6].
    $expectedLastDate = today()->addDays(7)->format('Y-m-d');

    $screen->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-bar-0'
            && str_contains($n['props']['a11y_label'] ?? '', $expectedFirstDate))
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-bar-20'
            && str_contains($n['props']['a11y_label'] ?? '', $expectedLastDate))
        ->assertMissingElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-bar-21');
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
    ]);

    $screen = Native::test(Forecasts::class);
    $tree = $screen->tree();

    expect($tree['type'] ?? null)->toBe('refreshable');
    // Proves @refresh="refresh" is bound to THIS method specifically —
    // isset() alone would still pass for a typo like "refres" (see
    // ItemDetailScreenTest's "wraps its content in a refreshable" test).
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
