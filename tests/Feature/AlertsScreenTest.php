<?php

use App\NativeComponents\Screens\Alerts;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeAlertsScreenEndpoints(array $alerts = [], ?array $stats = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/alerts*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/alerts/*/read' => Http::response(['message' => 'Alerte marquée comme lue'], 200),
        '*/alerts/read-all' => Http::response(['message' => 'Alertes marquées comme lues', 'count' => count($alerts)], 200),
        '*/alerts/delete-read' => Http::response(['message' => 'Alertes lues supprimées', 'count' => 1], 200),
        '*/alerts/stats*' => Http::response(['stats' => $stats ?? ['total' => count($alerts), 'unread' => 0, 'critical_unread' => 0, 'today' => 0]], 200),
        '*/alerts*' => Http::response(['alerts' => $alerts, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => count($alerts)]], 200),
    ]);
}

function sampleAlert(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'type' => 'sales_drop',
        'type_label' => 'Baisse des ventes',
        'type_icon' => 'trending.down',
        'severity' => 'critical',
        'severity_color' => '#ef4444',
        'title' => 'Chute de revenus',
        'message' => 'Le CA a chuté de 30% cette semaine.',
        'is_read' => false,
        'is_sent' => true,
        'sent_at' => now()->toIso8601String(),
        'data' => [],
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}

it('shows the stat tiles', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()], stats: ['total' => 4, 'unread' => 2, 'critical_unread' => 1, 'today' => 1]);

    Native::test(Alerts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-stat-total' && ($n['props']['text'] ?? null) === '4')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-stat-unread' && ($n['props']['text'] ?? null) === '2')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-stat-critical' && ($n['props']['text'] ?? null) === '1')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-stat-today' && ($n['props']['text'] ?? null) === '1');
});

it('filters by severity using the real chip binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    $screen = Native::test(Alerts::class)
        ->assertElement('chip', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-severity-critical' && ($n['props']['on_change'] ?? null) === callbackIdFor("setSeverityFilter('critical')"));

    $screen->call('setSeverityFilter', 'critical');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ! str_contains((string) $request->url(), '/stats')
        && ($request['severity'] ?? null) === 'critical');
});

it('filters by type using the real select binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    $screen = Native::test(Alerts::class)
        ->assertElement('select', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-type-filter' && ($n['props']['on_change'] ?? null) === callbackIdFor('setTypeFilter'));

    $screen->select('alerts-type-filter', 'Baisse des ventes');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ! str_contains((string) $request->url(), '/stats')
        && ($request['type'] ?? null) === 'sales_drop');
});

it('resets the type filter when "Tous les types" is selected', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    $screen = Native::test(Alerts::class);
    $screen->select('alerts-type-filter', 'Baisse des ventes');
    $screen->select('alerts-type-filter', 'Tous les types');

    expect($screen->get('typeFilter'))->toBeNull();

    // Http::assertSent() matches ANY recorded request, and the initial mount
    // fetch already carries no `type` key — so a plain assertSent() here
    // would pass even if the reset never re-fetched. Http::recorded() lets
    // us isolate the LAST /alerts list request (post-reset) specifically.
    $lastListRequest = Http::recorded(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ! str_contains((string) $request->url(), '/stats'))->last()[0];

    expect($lastListRequest['type'] ?? null)->toBeNull();
});

// StockAlertsTabTest carries two tests for its equivalent chip (binding +
// behavior) precisely because a chip's `@press` silently binds nothing —
// only `@change`/`on_change` works — and because the unreadOnly ->
// is_read=false inversion is easy to get backwards silently. Same shape
// here, so the same coverage.
it('wires the unread-only chip to on_change on toggleUnreadOnly', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    Native::test(Alerts::class)
        ->assertElement('chip', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-unread-only'
            && ($n['props']['on_change'] ?? null) === callbackIdFor('toggleUnreadOnly'));
});

it('requests unread-only alerts when the chip is tapped', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    Native::test(Alerts::class)->toggle('alerts-unread-only', true);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ! str_contains((string) $request->url(), '/stats')
        && ($request['is_read'] ?? null) === false);
});

// The "Toutes" reset chip bakes a literal `null` into the expression
// (`setSeverityFilter(null)`), a different CallbackRegistry::parse() path
// than the string-literal case already covered above. If that literal were
// ever decoded as the STRING "null" instead of PHP null, array_filter()'s
// `!== null` guard in refresh() would keep it and every reset would send
// severity=null to the API instead of omitting the param. Asserting on the
// resulting component state (not just that a request fired) is what would
// catch that regression.
it('clears the severity filter via the real "Toutes" chip binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    $screen = Native::test(Alerts::class);
    $screen->call('setSeverityFilter', 'critical');
    expect($screen->get('severityFilter'))->toBe('critical');

    $screen->toggle('alerts-severity-all', true);
    expect($screen->get('severityFilter'))->toBeNull();

    $lastListRequest = Http::recorded(fn ($request) => str_contains((string) $request->url(), '/alerts?')
        && ! str_contains((string) $request->url(), '/stats'))->last()[0];

    expect($lastListRequest['severity'] ?? null)->toBeNull();
});

it('marks a single alert as read via the real button binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert(['id' => 5])]);

    $screen = Native::test(Alerts::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'alert-5-mark-read' && ($n['props']['on_press'] ?? null) === callbackIdFor('markAsRead(5)'));

    $screen->call('markAsRead', 5);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts/5/read'));
    expect($screen->get('alerts')[0]['is_read'])->toBeTrue();
});

it('does not show the mark-read button for an already-read alert', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert(['id' => 1, 'is_read' => true])]);

    Native::test(Alerts::class)
        ->assertMissingElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'alert-1-mark-read')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alert-1-title');
});

it('marks all alerts as read via the real button binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    Native::test(Alerts::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-mark-all-read' && ($n['props']['on_press'] ?? null) === callbackIdFor('markAllAsRead'))
        ->call('markAllAsRead');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts/read-all'));
});

it('deletes read alerts via the real button binding', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert(['is_read' => true])]);

    Native::test(Alerts::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-delete-read' && ($n['props']['on_press'] ?? null) === callbackIdFor('deleteRead'))
        ->call('deleteRead');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/alerts/delete-read'));
});

it('shows an empty state when there are no alerts', function () {
    fakeAlertsScreenEndpoints(alerts: []);

    Native::test(Alerts::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeAlertsScreenEndpoints(error: 'boom');

    Native::test(Alerts::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-error');
});

// Bug-shape check mirroring StockAlertsTabTest's own regression test: the
// refreshable wrapper must be the OUTERMOST element (wrapping stats,
// filters, actions AND the list), not just wrapped around the list, or the
// screen won't claim the remaining vertical space / won't pull-to-refresh
// the whole screen. Paired with positive assertions so a silently-broken
// render (the whole tree missing) can't pass this test vacuously.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    $screen = Native::test(Alerts::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-stat-total')
        ->assertElement('select', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-type-filter')
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'alerts-mark-all-read');
});

it('is fully accessible', function () {
    fakeAlertsScreenEndpoints(alerts: [sampleAlert()]);

    Native::test(Alerts::class)->assertAccessible();
});
