<?php

use App\NativeComponents\ForecastSection;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeForecastsEndpoint(array $historical = [], array $forecasts = [], ?array $summary = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/forecasts*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
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

    Native::test(ForecastSection::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence' && str_contains($n['props']['text'] ?? '', '82'));

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/forecasts') && ! isset($request['product_id']));
});

// Tailwind classes resolve into a `style.bg_color` hex on the wire node
// (confirmed by dumping the compiled tree for this exact scenario) — there
// is no literal `class` string left in `props` to str_contains() against,
// unlike text/font props. So this compares the actually-resolved colors:
// the historical bar must resolve the primary token (#0D9488, config/native-ui.php)
// and the forecast bar the accent token (#F59E0B), proving the chart visually
// distinguishes historical-vs-forecast days rather than reusing one color.
it('renders a bar per historical and forecast day, with distinct colors for historical vs forecast', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['date' => today()->format('Y-m-d')])], forecasts: [sampleForecastDay(['forecast_date' => today()->addDay()->format('Y-m-d')])]);

    $tree = Native::test(ForecastSection::class)->tree();

    $bar0 = findNodeByRef($tree, 'forecast-bar-0');
    $bar1 = findNodeByRef($tree, 'forecast-bar-1');

    expect($bar0)->not->toBeNull();
    expect($bar1)->not->toBeNull();
    expect($bar0['style']['bg_color'] ?? null)->toContain('0D9488');
    expect($bar1['style']['bg_color'] ?? null)->toContain('F59E0B');
    expect($bar0['style']['bg_color'] ?? null)->not->toBe($bar1['style']['bg_color'] ?? null);
});

// `<rect @press>` bars compile with the callback id at the wire node's TOP
// LEVEL (on_press), the same as Dashboard's own chart bars — not nested
// under props like `<button @press>`. Confirmed against
// tests/Feature/DashboardScreenTest.php's `dashboard-chart-bar-0` assertion.
it('selects a bar via the real binding and shows a Réalisé/Prévu readout', function () {
    fakeForecastsEndpoint(historical: [sampleHistoricalDay(['revenue' => 88.5])], forecasts: []);

    $screen = Native::test(ForecastSection::class)
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

    $screen = Native::test(ForecastSection::class)
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

    $screen = Native::test(ForecastSection::class);
    $screen->set('productSearch', 'bleu');
    $screen->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-product-result-5' && ($n['on_press'] ?? null) === callbackIdFor("selectProduct(5, 'T-shirt bleu')"));

    $screen->call('selectProduct', 5, 'T-shirt bleu');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/forecasts') && ($request['product_id'] ?? null) === 5);
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-selected-product' && str_contains($n['props']['text'] ?? '', 'T-shirt bleu'))
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-clear-product' && ($n['props']['on_press'] ?? null) === callbackIdFor('clearProduct'));
});

it('clears the selected product via the real binding and returns to the shop-level view', function () {
    fakeForecastsEndpoint();

    $screen = Native::test(ForecastSection::class);
    $screen->call('selectProduct', 5, 'T-shirt bleu');

    $screen->call('clearProduct');

    $screen->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-selected-product')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-confidence'); // paired positive assertion — confirms the shop-level view genuinely re-rendered, not just that the product header vanished
});

it('shows a generic error instead of crashing on failure', function () {
    fakeForecastsEndpoint(error: 'boom');

    Native::test(ForecastSection::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'forecast-error');
});
