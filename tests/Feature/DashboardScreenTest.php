<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeDashboardEndpoints(): void
{
    Http::fake([
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
        ->assertElement('rect');
});

it('refetches data on pull-to-refresh', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')->call('refresh');

    Http::assertSentCount(8); // 4 endpoints on mount + 4 again on refresh
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
    ]);

    Native::visit('/dashboard')->assertSee('Server Error');
});
