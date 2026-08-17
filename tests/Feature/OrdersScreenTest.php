<?php

use App\NativeComponents\Screens\Orders;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeOrdersEndpoints(array $orders = [], ?array $pagination = null, ?string $error = null): void
{
    // Orders now loads the shared header's account data too (HasHeaderChrome)
    // — fake /auth/me so that call succeeds and doesn't clobber $lastApiError
    // ahead of (or, in the $error case, get overwritten by) the /orders call.
    if ($error !== null) {
        Http::fake([
            '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
            '*/orders*' => Http::response(['message' => $error], 500),
        ]);

        return;
    }

    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
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

// Every list fetch is silently scoped to the last 30 days (start_date), so a
// flat "not found" empty state reads as "this order doesn't exist" rather
// than "search your date range" — especially when searching for an order
// outside the window. The empty state must name the window so it's obvious
// a date filter, not a missing order, is in play.
it('mentions the 30-day search window in the empty state', function () {
    fakeOrdersEndpoints(orders: []);

    $screen = Native::test(Orders::class);

    $emptyNode = findNodeByRef($screen->tree(), 'orders-empty');
    expect($emptyNode['props']['text'] ?? null)->toContain('30 derniers jours');
});

it('scopes every fetch to the fixed 30-day window via start_date', function () {
    fakeOrdersEndpoints(orders: []);

    Native::test(Orders::class);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/orders?')
        && ($request['start_date'] ?? null) === now()->subDays(30)->toDateString());
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
    //
    // Registered separately (and first) from the sequence below: the sequence
    // is scoped to '*/orders*' only, so Orders::refresh()'s loadCurrentUser()
    // call would otherwise be a stray request (see tests/Pest.php). The two
    // patterns are disjoint, so neither shadows the other.
    Http::fake(['*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200)]);

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

// Orders.php gained `use HasHeaderChrome;` (plus a loadCurrentUser() call in
// refresh()) so the shared header — now rendered via TabsLayout::navBar()
// on every screen in its nativeGroup, Orders included — has working buttons
// here instead of silently no-op'ing (NativeComponent's press dispatch does
// a bare method_exists check and returns early when a screen lacks the
// handler, rather than crashing).
it('wires the shared header actions (shop switcher, account sheet, alerts) onto the screen', function () {
    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.test']], 200),
        '*/shops*' => Http::response(['shops' => []], 200),
        '*/orders*' => Http::response(['orders' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]], 200),
    ]);

    $screen = Native::test(Orders::class);

    $screen->call('openShopSwitcher')
        ->assertSet('shopSheetOpen', true);

    $screen->call('openAccountSheet')
        ->assertSet('accountSheetOpen', true)
        ->assertSet('shopSheetOpen', false);

    $screen->call('goAlerts')
        ->assertNavigatedTo('/alerts');
});

it('is fully accessible', function () {
    fakeOrdersEndpoints(orders: [sampleOrder()]);

    Native::test(Orders::class)->assertAccessible();
});
