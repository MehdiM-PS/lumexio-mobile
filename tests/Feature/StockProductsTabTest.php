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
