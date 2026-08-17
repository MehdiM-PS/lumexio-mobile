<?php

use App\NativeComponents\Screens\Stock;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

// The 5-tab bar no longer has a Stock entry (TabsLayout was restructured to
// Accueil/Prévisions/Ventes/Reco/Alertes) — /stock stays in TabsLayout's
// nativeGroup and still gets the shared chrome, but with no tab of its own,
// so no tab highlights as active while it's on screen. Pending a later task
// in this SDD plan folding Stock's content into one of the 5 new tabs (or
// dropping it from the group).
it('shows the shared tab bar, with no tab of its own', function () {
    Http::fake([
        '*/alerts/stock-overview*' => Http::response(['stats' => ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0], 'valuation' => ['total_value' => 0, 'total_quantity' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
    ]);

    Native::visit('/stock')
        ->assertHasTab('Accueil')
        ->assertMissingElement('bottom_nav_item', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Stock');
});

it('shows the products tab by default and switches to alerts', function () {
    Http::fake([
        '*/alerts/stock-overview*' => Http::response(['stats' => ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0], 'valuation' => ['total_value' => 0, 'total_quantity' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
    ]);

    Native::visit('/stock')
        ->assertSee('Aucun produit trouvé.')
        ->set('activeTab', 1)
        ->assertSee('Aucune alerte.');
});

it('switches to the suppliers tab and mounts the nested StockSuppliersTab', function () {
    Http::fake([
        '*/alerts/stock-overview*' => Http::response(['stats' => ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0], 'valuation' => ['total_value' => 0, 'total_quantity' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/supplier-orders*' => Http::response(['orders' => ['data' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]], 'stats' => ['total' => 0, 'pending' => 0, 'pending_value' => 0.0, 'received_month' => 0]], 200),
        '*/suppliers*' => Http::response(['suppliers' => ['data' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]]], 200),
    ]);

    Native::visit('/stock')
        ->set('activeTab', 2)
        ->assertElement('tab_row', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-suppliers-subtabs');
});

// Stock.php gained `use HasHeaderChrome;` (plus HandlesApiErrors and a
// mount() loading the account) so the shared header — now rendered via
// TabsLayout::navBar() on every screen in its nativeGroup, Stock included —
// has working buttons here instead of silently no-op'ing (NativeComponent's
// press dispatch does a bare method_exists check and returns early when a
// screen lacks the handler, rather than crashing).
it('wires the shared header actions (shop switcher, account sheet, alerts) onto the screen', function () {
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/shops*' => Http::response(['shops' => []], 200),
        '*/alerts/stock-overview*' => Http::response(['stats' => ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0], 'valuation' => ['total_value' => 0, 'total_quantity' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
    ]);

    $screen = Native::test(Stock::class);

    $screen->call('openShopSwitcher')
        ->assertSet('shopSheetOpen', true);

    $screen->call('openAccountSheet')
        ->assertSet('accountSheetOpen', true)
        ->assertSet('shopSheetOpen', false);

    $screen->call('goAlerts')
        ->assertNavigatedTo('/alerts');
});

it('navigates to the item detail screen when a product row is selected', function () {
    Http::fake([
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [
            ['id' => 1, 'prestashop_id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10,
                'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7, 'price' => 20.0, 'price_ttc' => 24.0,
                'price_ht_discounted' => null, 'price_ttc_discounted' => null, 'wholesale_price' => 10.0,
                'is_active' => true, 'average_monthly_sales' => 3.0, 'days_until_stockout' => null,
                'demand_30d' => null, 'recommended_reorder_quantity' => null, 'recommended_reorder_date' => null,
                'expected_quantity' => 0, 'supplier_name' => null, 'category_name' => null],
        ], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 1]], 200),
    ]);

    Native::visit('/stock')
        ->call('onSelectItem', 'product', 1, ['type' => 'product', 'id' => 1, 'name' => 'T-shirt'])
        ->assertNavigatedTo('/stock/item/product/1');
});
