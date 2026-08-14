<?php

use App\NativeComponents\StockProductsTab;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeStockEndpoints(array $products = [], array $variants = []): void
{
    Http::fake([
        '*/products/variants*' => Http::response(['variants' => $variants, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => count($variants)]], 200),
        '*/products*' => Http::response(['products' => $products, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => count($products)]], 200),
    ]);
}

function stockProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'prestashop_id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1',
        'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7,
        'price' => 20.0, 'price_ttc' => 24.0, 'price_ht_discounted' => null, 'price_ttc_discounted' => null,
        'wholesale_price' => 10.0, 'is_active' => true, 'average_monthly_sales' => 3.0,
        'days_until_stockout' => 20, 'demand_30d' => 15, 'recommended_reorder_quantity' => null,
        'recommended_reorder_date' => null, 'expected_quantity' => 0,
        'supplier_name' => 'Acme', 'category_name' => 'Vêtements',
    ], $overrides);
}

function stockVariant(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'prestashop_id' => 1, 'product_id' => 1, 'name' => 'T-shirt - Rouge', 'reference' => 'TS-1-R',
        'price' => 20.0, 'price_ttc' => 24.0, 'price_ht_discounted' => null, 'price_ttc_discounted' => null,
        'quantity' => 4, 'low_stock_threshold' => 5, 'is_active' => true, 'attributes' => null,
        'average_monthly_sales' => 1.0, 'days_until_stockout' => 10, 'demand_30d' => 5,
        'recommended_reorder_quantity' => null, 'recommended_reorder_date' => null, 'expected_quantity' => 0,
        'supplier_name' => 'Acme', 'category_name' => 'Vêtements',
    ], $overrides);
}

it('excludes a parent product that has variants, showing only its variants', function () {
    fakeStockEndpoints(
        products: [stockProduct(['id' => 1, 'name' => 'T-shirt'])],
        variants: [stockVariant(['id' => 1, 'product_id' => 1, 'name' => 'T-shirt - Rouge'])],
    );

    Native::test(StockProductsTab::class)
        ->assertElement('pressable', fn ($n) => ($n['ref'] ?? null) === 'stock-item-variant-1')
        ->assertMissingElement('pressable', fn ($n) => ($n['ref'] ?? null) === 'stock-item-product-1')
        ->assertSee('T-shirt - Rouge');
});

it('shows a standalone product that has no variants', function () {
    fakeStockEndpoints(products: [stockProduct(['id' => 2, 'name' => 'Sac à dos'])], variants: []);

    Native::test(StockProductsTab::class)->assertSee('Sac à dos');
});

it('filters by search', function () {
    fakeStockEndpoints(products: [stockProduct(['id' => 3, 'name' => 'Casquette'])], variants: []);

    Native::test(StockProductsTab::class)
        ->set('search', 'casq')
        ->assertSee('Casquette');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?') && ($request['search'] ?? null) === 'casq');
});

it('shows an empty state when there are no items', function () {
    fakeStockEndpoints();

    Native::test(StockProductsTab::class)->assertSee('Aucun produit trouvé.');
});

it('shows a generic error instead of crashing on failure', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    Native::test(StockProductsTab::class)->assertSee('Connexion indisponible');
});

it('is fully accessible', function () {
    fakeStockEndpoints(products: [stockProduct()], variants: []);

    Native::test(StockProductsTab::class)->assertAccessible();
});

it('emits select-item when a row is tapped', function () {
    fakeStockEndpoints(products: [stockProduct(['id' => 4, 'name' => 'Casquette'])], variants: []);

    Native::test(StockProductsTab::class)->call('select', 'product', 4);
});

// Bug fix: the status filter chips were bound with `@press`, but Chip only
// exposes an `onChange()` callback (props.on_change) — it has no press/tap
// registration at all, so `@press` silently bound nothing and tapping a
// chip did nothing. Comparing against callbackIdFor() (content-addressed)
// rather than a plain isset() proves each chip is bound to the RIGHT
// setStatusFilter(...) call, not just bound to *something*.
it('wires each status chip to on_change with the correct filter', function () {
    fakeStockEndpoints(products: [stockProduct()], variants: []);

    Native::test(StockProductsTab::class)
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Tous'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("setStatusFilter('all')"))
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Stock bas'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("setStatusFilter('low')"))
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Rupture'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("setStatusFilter('out')"));
});

// Behavioral counterpart: actually fire the chip's wire event by its ref
// (as a real device tap would — resolved through the node's own on_change
// prop) rather than calling setStatusFilter() directly, and confirm it
// results in the correct API request.
//
// Deliberately targets by ref, NOT by the bare expression
// "setStatusFilter('low')": CallbackRegistry ids are content-addressed, so
// the broken `@press="setStatusFilter('low')"` binding registers the exact
// same id (base Element::toArray() unconditionally registers pressMethod
// as a top-level `on_press`, even though Chip::resolveProps() never reads
// it) — targeting by expression would pass whether the chip's callback is
// wired to on_press or on_change, i.e. it wouldn't catch this bug. Ref
// targeting forces the harness through callbackIdByRef(), which only
// finds the callback under props.on_change — proving the CHIP NODE itself,
// not just the registry, is bound correctly.
it('requests the low-stock filter when the "Stock bas" chip is tapped', function () {
    fakeStockEndpoints(products: [stockProduct()], variants: []);

    Native::test(StockProductsTab::class)->toggle('chip-status-low', true);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/products?') && ($request['filter'] ?? null) === 'low');
});

// Bug fix: <refreshable> was nested as one sibling among several inside the
// outer fill column, so it never claimed the remaining vertical space and
// the list didn't scroll. It must now be the OUTERMOST element (matching
// the working dashboard.blade.php pattern), wrapping the whole screen.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeStockEndpoints(products: [stockProduct()], variants: []);

    $screen = Native::test(StockProductsTab::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('outlined_text_input', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-search')
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Tous')
        ->assertElement('pressable');
});
