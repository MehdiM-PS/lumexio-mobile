<?php

use App\Models\LocalState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeDashboardEndpoints(array $shops = []): void
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
        ->assertElement('rect');
});

it('refetches data on pull-to-refresh', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard')->call('refresh');

    Http::assertSentCount(10); // 5 endpoints on mount + 5 again on refresh
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

it('defaults to today and requests metrics with day=today', function () {
    fakeDashboardEndpoints();

    Native::visit('/dashboard');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'today');
});

it('switches to yesterday and re-fetches metrics with day=yesterday', function () {
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
    fakeDashboardEndpoints();

    $screen = Native::visit('/dashboard');
    $screen->fireEvent('dashboard-day-scope', $screen::EVENT_TAB_CHANGE, ['value' => 1]);

    expect($screen->get('dayScope'))->toBe(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/dashboard/metrics')
        && ($request['day'] ?? null) === 'yesterday');
});
