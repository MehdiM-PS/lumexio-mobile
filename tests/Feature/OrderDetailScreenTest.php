<?php

use App\NativeComponents\Screens\OrderDetail;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeOrderDetailEndpoint(array $order, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/orders/*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake(['*/orders/*' => Http::response(['order' => $order], 200)]);
}

// Bug fix precedent: an inline <top-bar> (no NativeLayout — this screen is a
// top-level route outside TabsLayout's nativeGroup) gives the screen the
// real NavigationStack-backed chrome: automatic top safe-area handling AND
// a back chevron. The inline bar is hoisted out of the content tree into the
// `native_root_stack` sentinel's own props (NOT rendered as a `top_bar`
// element in-tree) — see NativeComponent::wrapWithChrome()/
// wrapWithNativeChrome() in vendor/nativephp/mobile.
//
// `back` is explicit (not left to "pushed screens get it for free"):
// OrderDetail publishes its OWN `native_root_stack`, separate from the tab
// (Orders) it's pushed from — a different Swift coordinator
// (PerTabNavigationCoordinator vs the singleton NavigationCoordinator). Per
// NavigationCoordinator.swift, that stack's `rootUri` is seeded from the
// FIRST uri it ever sees — which is OrderDetail's own URI, making it
// `isRoot: true` on ITS stack even though PHP's router considers it pushed.
// NativeRootStackRenderer.swift only draws a manual chevron when
// `showBack && isRoot`, so without an explicit `back` this screen would
// have no way out. (Verified via source inspection only — no iOS simulator
// available in this environment.)
it('renders an inline top bar with the order reference as the title and a back button, hoisted into native chrome', function () {
    fakeOrderDetailEndpoint(['id' => 1, 'reference' => 'ORD-001', 'items' => [], 'customer' => null, 'total_paid_tax_excl' => 0, 'purchase_cost' => 0, 'fixed_fee' => 0, 'margin' => 0, 'margin_rate' => 0]);

    Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertNavTitle('ORD-001')
        ->assertElement('native_root_stack', fn (array $n): bool => ($n['props']['title'] ?? null) === 'ORD-001' && ($n['props']['back'] ?? null) === true)
        ->assertMissingElement('top_bar');
});

// mount() calls loadDetail() synchronously, so asserting against a
// successful fetch that returns the SAME reference as the nav data proves
// nothing — a mount() that never read $this->data('order', ...) at all
// would still pass, since loadDetail()'s own API response would set the
// same value. The nav-data seed is only observable in the one window before
// it's overwritten: when the fetch fails, so $this->order is never
// replaced. Assert against that window instead.
it('keeps painting the navigation-data order when the detail fetch fails', function () {
    fakeOrderDetailEndpoint([], error: 'boom');

    $screen = Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001', 'total_paid' => 50.0]]);

    expect($screen->get('order')['reference'])->toBe('ORD-001');
    $screen->assertNavTitle('ORD-001');
});

// Same failure-path trick, but exercises mount()'s OTHER fallback: when no
// nav data is passed at all, $this->order falls back to the route param.
it('falls back to the route id when no navigation data is passed and the detail fetch fails', function () {
    fakeOrderDetailEndpoint([], error: 'boom');

    $screen = Native::test(OrderDetail::class, params: ['id' => 7], data: []);

    expect($screen->get('order')['id'])->toBe(7);
});

it('hydrates line items and margin after loadDetail resolves', function () {
    fakeOrderDetailEndpoint([
        'id' => 1,
        'reference' => 'ORD-001',
        'items' => [['id' => 1, 'product_name' => 'T-shirt', 'quantity' => 2, 'unit_price' => 10.0, 'total_price' => 20.0]],
        'customer' => ['id' => 1, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'email' => 'jean@example.com'],
        'total_paid_tax_excl' => 20.0,
        'purchase_cost' => 6.0,
        'fixed_fee' => 1.0,
        'margin' => 13.0,
        'margin_rate' => 65.0,
    ]);

    $screen = Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']]);

    expect($screen->get('order')['items'])->toHaveCount(1);
    // ->toEqual(), not ->toBe(): Http::fake() round-trips the response body
    // through json_encode()/json_decode() exactly like a real HTTP response
    // would, and PHP's JSON encoder collapses whole-number floats (13.0) to
    // ints (13) — so a strict ===-based toBe(13.0) would fail here even
    // though loadDetail() faithfully passed the decoded value through.
    expect($screen->get('order')['margin'])->toEqual(13.0);
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'order-detail-item-1-name' && ($n['props']['text'] ?? null) === 'T-shirt');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeOrderDetailEndpoint([], error: 'boom');

    Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'order-detail-error');
});

// Proves the refreshable is bound to loadDetail() via the REAL @refresh
// binding (Refreshable::resolveProps()'s content-addressed CallbackRegistry
// id) rather than just calling loadDetail() directly — an isset() check
// alone would still pass for a typo like "loadDetial".
it('wraps its content in a refreshable element wired to loadDetail', function () {
    fakeOrderDetailEndpoint(['id' => 1, 'reference' => 'ORD-001', 'items' => [], 'customer' => null, 'total_paid_tax_excl' => 0, 'purchase_cost' => 0, 'fixed_fee' => 0, 'margin' => 0, 'margin_rate' => 0]);

    Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertElement('refreshable', fn (array $n): bool => ($n['props']['on_refresh'] ?? null) === callbackIdFor('loadDetail'));
});

it('shows the status badge, customer, and margin breakdown once hydrated', function () {
    fakeOrderDetailEndpoint([
        'id' => 1,
        'reference' => 'ORD-001',
        'order_date' => '2026-08-10T10:00:00+00:00',
        'total_paid' => 20.0,
        'status_label' => 'Livrée',
        'status_color' => '#10b981',
        'status_text_color' => '#ffffff',
        'items' => [],
        'customer' => ['id' => 1, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'email' => 'jean@example.com'],
        'total_paid_tax_excl' => 20.0,
        'purchase_cost' => 6.0,
        'fixed_fee' => 1.0,
        'margin' => 13.0,
        'margin_rate' => 65.0,
    ]);

    $screen = Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertSee('Livrée')
        ->assertSee('Dupont')
        ->assertSee('jean@example.com');

    // The badge's background and the status text's foreground must resolve
    // from status_color/status_text_color's runtime-interpolated hex values
    // (via the bg-[...]/text-[...] arbitrary-value classes), not just render
    // the label text — a silently-dropped/unparsed class would still pass
    // the assertSee() checks above. Mirrors OrdersScreenTest's equivalent
    // check on the list screen's own status badge.
    $statusNode = findNodeByRef($screen->tree(), 'order-detail-status');
    expect($statusNode['props']['color'] ?? null)->toBe('#FFFFFF');

    $badgeNode = findNodeByRef($screen->tree(), 'order-detail-status-badge');
    expect($badgeNode['style']['bg_color'] ?? null)->toBe('#10B981');
});

// margin_rate is nullable (per the API: only when total_paid_tax_excl is 0)
// — a real, documented state, distinct from the 0/absent cases already
// covered elsewhere. Guards against a regression from the view's explicit
// `!== null` check to a truthy check (`@if ($order['margin_rate'])`), which
// would silently swallow a legitimate 0,0% and drop the whole parenthetical
// rather than just the percentage.
it('shows the margin without a percentage when margin_rate is null', function () {
    fakeOrderDetailEndpoint([
        'id' => 1,
        'reference' => 'ORD-001',
        'items' => [],
        'customer' => null,
        'total_paid_tax_excl' => 0,
        'purchase_cost' => 0,
        'fixed_fee' => 0,
        'margin' => 0,
        'margin_rate' => null,
    ]);

    Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertSee('Marge')
        ->assertMissingElement('text', fn (array $n): bool => str_contains((string) ($n['props']['text'] ?? ''), '%'));
});

// Regression test for the HT/TTC margin-percentage mismatch: margin_rate is
// margin / total_paid_tax_excl (HT), not margin / total_paid (TTC), so the
// screen must show both figures distinctly and labeled — using refs rather
// than assertSee(), since two "48,00 €"/"40,00 €"-shaped euro amounts
// sitting this close together are exactly the kind of substring collision
// this screen has hit before.
it('shows Total HT distinctly from Total TTC so it can be verified against the margin percentage', function () {
    fakeOrderDetailEndpoint([
        'id' => 1,
        'reference' => 'ORD-001',
        'items' => [],
        'customer' => null,
        'total_paid' => 48.0,
        'total_paid_tax_excl' => 40.0,
        'purchase_cost' => 6.0,
        'fixed_fee' => 5.0,
        'margin' => 29.0,
        'margin_rate' => 72.5,
    ]);

    $screen = Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']]);

    $ttcNode = findNodeByRef($screen->tree(), 'order-detail-total-ttc');
    expect($ttcNode['props']['text'] ?? null)->toBe('48,00 €');

    $htNode = findNodeByRef($screen->tree(), 'order-detail-total-ht');
    expect($htNode['props']['text'] ?? null)->toBe('40,00 €');

    // 29,00 / 40,00 = 72,5% — the reader should be able to verify this by
    // eye with Total HT and Marge sitting adjacent, unlike 29,00 / 48,00
    // (60,4%) which is what a merchant would naturally try against TTC.
    $screen->assertElement('text', fn (array $n): bool => str_contains((string) ($n['props']['text'] ?? ''), '29,00 €') && str_contains((string) ($n['props']['text'] ?? ''), '72,5%'));
});

it('refetches the order detail on pull-to-refresh', function () {
    Http::fakeSequence('*/orders/*')
        ->push(['order' => ['id' => 1, 'reference' => 'ORD-001', 'items' => [], 'customer' => null, 'total_paid_tax_excl' => 0, 'purchase_cost' => 0, 'fixed_fee' => 0, 'margin' => 0, 'margin_rate' => 0]])
        ->push(['order' => ['id' => 1, 'reference' => 'ORD-001', 'items' => [], 'customer' => null, 'total_paid_tax_excl' => 11.0, 'purchase_cost' => 2.0, 'fixed_fee' => 1.0, 'margin' => 8.0, 'margin_rate' => 40.0]]);

    $screen = Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']]);

    // ->toEqual(), not ->toBe(): see the note in the "hydrates line items
    // and margin" test above — whole-number floats round-trip through
    // Http::fake()'s JSON encoding as ints.
    expect($screen->get('order')['margin'])->toEqual(0.0);

    $screen->call('loadDetail');

    expect($screen->get('order')['margin'])->toEqual(8.0);
});

it('is fully accessible', function () {
    fakeOrderDetailEndpoint([
        'id' => 1,
        'reference' => 'ORD-001',
        'order_date' => '2026-08-10T10:00:00+00:00',
        'total_paid' => 20.0,
        'status_label' => 'Livrée',
        'status_color' => '#10b981',
        'status_text_color' => '#ffffff',
        'items' => [['id' => 1, 'product_name' => 'T-shirt', 'quantity' => 2, 'unit_price' => 10.0, 'total_price' => 20.0]],
        'customer' => ['id' => 1, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'email' => 'jean@example.com'],
        'total_paid_tax_excl' => 20.0,
        'purchase_cost' => 6.0,
        'fixed_fee' => 1.0,
        'margin' => 13.0,
        'margin_rate' => 65.0,
    ]);

    Native::test(OrderDetail::class, params: ['id' => 1], data: ['order' => ['id' => 1, 'reference' => 'ORD-001']])
        ->assertAccessible();
});
