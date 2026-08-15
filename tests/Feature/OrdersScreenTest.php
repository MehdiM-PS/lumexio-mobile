<?php

use App\NativeComponents\Screens\Orders;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeOrdersEndpoints(array $orders = [], ?array $pagination = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/orders*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/orders*' => Http::response([
            'orders' => $orders,
            'pagination' => $pagination ?? [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => count($orders),
            ],
        ], 200),
    ]);
}

function sampleOrder(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'reference' => 'ORD-001',
        'order_date' => '2026-08-10T10:00:00+00:00',
        'total_paid' => 99.90,
        'status' => 'delivered',
        'status_label' => 'Livrée',
        'status_color' => '#10b981',
        'status_text_color' => '#ffffff',
        'customer' => ['id' => 1, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'email' => 'jean@example.com'],
    ], $overrides);
}

it('shows the order list with reference, customer, and status badge', function () {
    fakeOrdersEndpoints(orders: [sampleOrder()]);

    $screen = Native::test(Orders::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'order-1-reference' && ($n['props']['text'] ?? null) === 'ORD-001')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'order-1-status' && ($n['props']['text'] ?? null) === 'Livrée');

    // The badge's background and the status text's foreground must resolve
    // from status_color/status_text_color's runtime-interpolated hex values
    // (via the bg-[...]/text-[...] arbitrary-value classes), not just render
    // the label text — a silently-dropped/unparsed class would still pass
    // the assertions above.
    $statusNode = findNodeByRef($screen->tree(), 'order-1-status');
    expect($statusNode['props']['color'] ?? null)->toBe('#FFFFFF');

    $badgeNode = findNodeByRef($screen->tree(), 'order-1-badge');
    expect($badgeNode['style']['bg_color'] ?? null)->toBe('#10B981');
});

// Proves the row is bound to selectOrder(1) via the REAL @press binding
// (CallbackRegistry::parse()), not just that calling selectOrder(1) by hand
// works. callbackIdFor() is content-addressed (a pure hash of the
// expression string), so comparing against it — rather than an isset()
// check on on_press — would catch both a typo in the baked expression and a
// regression back to baking the whole order object instead of just its id
// (the exact mistake the task brief called out and this screen deliberately
// avoids, per NativeComponents/Screens/Orders.php's selectOrder()).
it('wires each order row to selectOrder with its own baked-in id', function () {
    fakeOrdersEndpoints(orders: [
        sampleOrder(['id' => 1, 'reference' => 'ORD-001']),
        sampleOrder(['id' => 2, 'reference' => 'ORD-002']),
    ]);

    Native::test(Orders::class)
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'order-1'
            && ($n['on_press'] ?? null) === callbackIdFor('selectOrder(1)'))
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'order-2'
            && ($n['on_press'] ?? null) === callbackIdFor('selectOrder(2)'));
});

it('shows an em dash when the order has no customer', function () {
    fakeOrdersEndpoints(orders: [sampleOrder(['customer' => null])]);

    Native::test(Orders::class)->assertSee('—');
});

it('shows an empty state when there are no orders', function () {
    fakeOrdersEndpoints(orders: []);

    Native::test(Orders::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'orders-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeOrdersEndpoints(error: 'boom');

    Native::test(Orders::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'orders-error');
});

it('re-fetches with the search term as the customer/reference filter', function () {
    fakeOrdersEndpoints(orders: [sampleOrder()]);

    $screen = Native::test(Orders::class);
    $screen->set('search', 'dupont');
    $screen->call('refresh');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/orders?') && ($request['search'] ?? null) === 'dupont');
});

it('loads more orders and appends them without losing the first page', function () {
    // A plain Http::fake(['*/orders*' => ...]) called twice does NOT override
    // the first stub for the second request: Laravel's HTTP client matches
    // stubs in registration order and uses the first match
    // (PendingRequest::buildStubHandler() -> ->filter()->first()), so the
    // page-1 fake would still answer the page-2 request. A fakeSequence()
    // hands out its pushed responses in call order instead, which is what
    // this test — mount (page 1) then loadMore() (page 2) — needs.
    Http::fakeSequence('*/orders*')
        ->push([
            'orders' => [sampleOrder(['id' => 1, 'reference' => 'ORD-001'])],
            'pagination' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
        ])
        ->push([
            'orders' => [sampleOrder(['id' => 2, 'reference' => 'ORD-002'])],
            'pagination' => ['current_page' => 2, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
        ]);

    $screen = Native::test(Orders::class);
    expect($screen->get('orders'))->toHaveCount(1);
    expect($screen->get('hasMorePages'))->toBeTrue();
    $screen->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'orders-load-more');

    $screen->call('loadMore');

    expect($screen->get('orders'))->toHaveCount(2);
    expect($screen->get('hasMorePages'))->toBeFalse();
    $screen->assertMissingElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'orders-load-more');
});

it('navigates to the order detail screen when a row is selected, looking up the order by id', function () {
    fakeOrdersEndpoints(orders: [sampleOrder(['id' => 1, 'reference' => 'ORD-001'])]);

    Native::test(Orders::class)
        ->call('selectOrder', 1)
        ->assertNavigatedTo('/orders/1');
});

it('is fully accessible', function () {
    fakeOrdersEndpoints(orders: [sampleOrder()]);

    Native::test(Orders::class)->assertAccessible();
});
