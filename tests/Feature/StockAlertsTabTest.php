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
