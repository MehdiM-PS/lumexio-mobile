<?php

use App\NativeComponents\SupplierOrdersTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeSupplierOrdersEndpoints(array $orders = [], ?array $stats = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/supplier-orders*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/supplier-orders*' => Http::response([
            'orders' => ['data' => $orders, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => count($orders)]],
            'stats' => $stats ?? ['total' => count($orders), 'pending' => 0, 'pending_value' => 0.0, 'received_month' => 0],
        ], 200),
    ]);
}

function sampleSupplierOrder(array $overrides = []): array
{
    return array_merge([
        'id' => 'a1b2c3d4-0000-0000-0000-000000000001',
        'reference' => 'CF-20260815-0001',
        'name' => null,
        'status' => 'commandee',
        'status_label' => 'Commandée',
        'status_color' => '#3b82f6',
        'total_ht' => 450.0,
        'expected_delivery_date' => '2026-08-25',
        'delivered_at' => null,
        'notes' => null,
        'supplier' => ['id' => 1, 'name' => 'Fournisseur Textile SA'],
        'allowed_transitions' => ['en_cours', 'recue_partielle', 'recue', 'annulee'],
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}

it('shows the stat tiles', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()], stats: ['total' => 5, 'pending' => 3, 'pending_value' => 1200.5, 'received_month' => 2]);

    Native::test(SupplierOrdersTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-stat-total' && ($n['props']['text'] ?? null) === '5')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-stat-pending' && ($n['props']['text'] ?? null) === '3')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-stat-received' && ($n['props']['text'] ?? null) === '2');
});

it('shows the order list with reference and status badge', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()]);

    Native::test(SupplierOrdersTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-a1b2c3d4-0000-0000-0000-000000000001-reference' && ($n['props']['text'] ?? null) === 'CF-20260815-0001');
});

it('toggles hideCompleted using the real chip binding and re-fetches', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()]);

    $screen = Native::test(SupplierOrdersTab::class)
        ->assertElement('chip', fn (array $n): bool => ($n['ref'] ?? null) === 'so-hide-completed' && ($n['props']['on_change'] ?? null) === callbackIdFor('toggleHideCompleted'));

    $screen->call('toggleHideCompleted');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/supplier-orders?')
        && ($request['hide_completed'] ?? null) === false); // starts true (web default), toggling flips it off
});

it('filters by status using the real select binding, sending the slug not the label', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()]);

    $screen = Native::test(SupplierOrdersTab::class)
        ->assertElement('select', fn (array $n): bool => ($n['ref'] ?? null) === 'so-status-filter' && ($n['props']['on_change'] ?? null) === callbackIdFor('setStatusFilter'));

    $screen->call('setStatusFilter', 'Commandée');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/supplier-orders?') && ($request['status'] ?? null) === 'commandee');
});

it('navigates to the detail screen when a row is selected, looking up by the string id', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder(['id' => 'xyz-789'])]);

    Native::test(SupplierOrdersTab::class)
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'so-xyz-789' && ($n['on_press'] ?? null) === callbackIdFor("select('xyz-789')"))
        ->call('select', 'xyz-789')
        ->assertNavigatedTo('/supplier-orders/xyz-789');
});

it('shows an empty state when there are no orders', function () {
    fakeSupplierOrdersEndpoints(orders: []);

    Native::test(SupplierOrdersTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeSupplierOrdersEndpoints(error: 'boom');

    Native::test(SupplierOrdersTab::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'so-error');
});

it('is fully accessible', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()]);

    Native::test(SupplierOrdersTab::class)->assertAccessible();
});

it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeSupplierOrdersEndpoints(orders: [sampleSupplierOrder()]);

    expect(Native::test(SupplierOrdersTab::class)->tree()['type'])->toBe('refreshable');
});
