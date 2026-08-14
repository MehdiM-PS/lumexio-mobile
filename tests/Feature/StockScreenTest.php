<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('shows the tab bar with a Stock tab', function () {
    Http::fake([
        '*/alerts/stock-overview*' => Http::response(['stats' => ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0], 'valuation' => ['total_value' => 0, 'total_quantity' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products/variants*' => Http::response(['variants' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
        '*/products*' => Http::response(['products' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0]], 200),
    ]);

    Native::visit('/stock')
        ->assertHasTab('Dashboard')
        ->assertHasTab('Stock')
        ->assertTabActive('Stock');
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
