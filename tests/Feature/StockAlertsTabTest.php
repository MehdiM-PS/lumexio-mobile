<?php

use App\NativeComponents\StockAlertsTab;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeAlertEndpoints(array $alerts = [], ?array $overview = null): void
{
    Http::fake([
        '*/alerts/stock-overview*' => Http::response($overview ?? [
            'stats' => ['total' => 10, 'low_stock' => 3, 'out_of_stock' => 1],
            'valuation' => ['total_value' => 5000.0, 'total_quantity' => 200],
        ], 200),
        '*/alerts/read-all*' => Http::response(['message' => 'Alertes marquées comme lues', 'count' => count($alerts)], 200),
        '*/alerts/*/read' => Http::response(['message' => 'Alerte marquée comme lue'], 200),
        '*/alerts*' => Http::response(['alerts' => $alerts, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => count($alerts)]], 200),
    ]);
}

function stockAlert(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'type' => 'stock_low', 'title' => 'Stock bas', 'message' => 'Le produit X est bas.',
        'severity' => 'warning', 'is_read' => false, 'data' => null, 'created_at' => '2026-08-14T10:00:00+00:00',
    ], $overrides);
}

it('shows the stock overview stats', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    // Not assertSee('Stock bas'): the fixture alert's title is ALSO "Stock
    // bas", and treeContainsText() does a plain str_contains() over every
    // visible-text prop in the tree — it can't tell whether the match came
    // from the stat's label or the alert row's title. assertElement() on
    // the stat's own `ref` targets the stat node specifically.
    Native::test(StockAlertsTab::class)
        ->assertSee('10')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stat-low-stock' && ($n['props']['text'] ?? null) === '3');
});

it('filters by unread-only status', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    Native::test(StockAlertsTab::class)->call('toggleUnreadOnly');

    // toggleUnreadOnly() flips $unreadOnly from null to true, and refresh()
    // maps that to ['is_read' => ! $this->unreadOnly] — i.e. "unread only"
    // must request is_read=false (unread), not is_read=true. The double
    // negation is easy to get backwards silently, so assert the actual
    // query param sent rather than just that a request fired.
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ($request['is_read'] ?? null) === false);
});

it('marks a single alert as read', function () {
    fakeAlertEndpoints(alerts: [stockAlert(['id' => 5])]);

    Native::test(StockAlertsTab::class)
        ->call('markAsRead', 5);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts/5/read'));
});

it('marks all alerts as read', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    Native::test(StockAlertsTab::class)
        ->call('markAllAsRead');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts/read-all'));
});

it('shows an empty state when there are no alerts', function () {
    fakeAlertEndpoints(alerts: []);

    Native::test(StockAlertsTab::class)->assertSee('Aucune alerte.');
});

it('shows a generic error instead of crashing on failure', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    Native::test(StockAlertsTab::class)->assertSee('Connexion indisponible');
});

it('is fully accessible', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    Native::test(StockAlertsTab::class)->assertAccessible();
});

// Bug fix: the "Non lues uniquement" chip was bound with `@press`, but Chip
// only exposes an `onChange()` callback (props.on_change) — it has no
// press/tap registration at all, so `@press` silently bound nothing.
// Comparing against callbackIdFor() (content-addressed) proves it's bound
// to THIS method, not just bound to something.
it('wires the unread-only chip to on_change on toggleUnreadOnly', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    Native::test(StockAlertsTab::class)
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Non lues uniquement'
            && ($n['props']['on_change'] ?? null) === callbackIdFor('toggleUnreadOnly'));
});

// Behavioral counterpart: actually fire the chip's wire event by its ref
// (as a real device tap would — resolved through the node's own on_change
// prop) rather than calling toggleUnreadOnly() directly, and confirm it
// results in the correct API request.
//
// Deliberately targets by ref, NOT by the bare method name
// "toggleUnreadOnly": CallbackRegistry ids are content-addressed, so the
// broken `@press="toggleUnreadOnly"` binding registers the exact same id
// (base Element::toArray() unconditionally registers pressMethod as a
// top-level `on_press`, even though Chip::resolveProps() never reads it)
// — targeting by method name would pass whether the chip's callback is
// wired to on_press or on_change, i.e. it wouldn't catch this bug. Ref
// targeting forces the harness through callbackIdByRef(), which only
// finds the callback under props.on_change — proving the CHIP NODE itself,
// not just the registry, is bound correctly.
it('requests unread-only alerts when the chip is tapped', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    Native::test(StockAlertsTab::class)->toggle('chip-unread-only', true);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ($request['is_read'] ?? null) === false);
});

// Bug fix: <refreshable> was nested as one sibling among several inside the
// outer fill column, so it never claimed the remaining vertical space and
// the alert list didn't scroll. It must now be the OUTERMOST element
// (matching the working dashboard.blade.php pattern), wrapping the whole
// screen — stats, chip/button row, and the list.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeAlertEndpoints(alerts: [stockAlert()]);

    $screen = Native::test(StockAlertsTab::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stat-total')
        ->assertElement('chip', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Non lues uniquement')
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'mark-all-read');
});
