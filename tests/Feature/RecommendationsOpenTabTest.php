<?php

use App\NativeComponents\RecommendationsOpenTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeRecommendationsOpenEndpoints(array $recommendations = [], ?array $stats = null, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/recommendations*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/recommendations/*/done' => Http::response(['message' => 'Recommandation marquée comme traitée'], 200),
        '*/recommendations/*/reject' => Http::response(['message' => 'Recommandation rejetée'], 200),
        '*/recommendations*' => Http::response([
            'recommendations' => $recommendations,
            'stats' => $stats ?? ['total' => count($recommendations), 'high_priority' => 0],
            'available_types' => [],
        ], 200),
    ]);
}

function sampleRecommendation(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'type' => 'stock_revenue_risk',
        'type_label' => 'Stock Revenue Risk',
        'group' => 'alerts',
        'group_label' => 'Alertes',
        'priority' => 'high',
        'priority_label' => 'Haute',
        'title' => 'Risque de rupture sur produit populaire',
        'description' => 'Ce produit va manquer de stock avant la prochaine livraison.',
        'data_fields' => [['label' => 'Produit', 'value' => 'T-shirt bleu']],
        'is_actioned' => false,
        'actioned_status' => null,
        'rejected_at' => null,
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}

it('shows recommendations grouped by their group label', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'group' => 'alerts', 'group_label' => 'Alertes']),
        sampleRecommendation(['id' => 2, 'group' => 'trends', 'group_label' => 'Tendances']),
    ]);

    Native::test(RecommendationsOpenTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-group-alerts-heading' && ($n['props']['text'] ?? null) === 'Alertes')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-group-trends-heading' && ($n['props']['text'] ?? null) === 'Tendances');
});

it('shows the total and high-priority stats', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()], stats: ['total' => 5, 'high_priority' => 2]);

    Native::test(RecommendationsOpenTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-stats-total' && ($n['props']['text'] ?? null) === '5')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-stats-high-priority' && ($n['props']['text'] ?? null) === '2');
});

// Bug fix: refresh() used to fall back to a hardcoded ['total' => 0,
// 'high_priority' => 0] whenever the API call failed (`$data['stats'] ??
// ['total' => 0, ...]`), so a merchant who pull-to-refreshed into a
// transient 500 saw both stat cards flash to 0 even though the last-known
// totals were still meaningful. It must fall back to the PRIOR $this->stats
// value instead, matching StockAlertsTab::refresh()'s
// `$overview['stats'] ?? $this->stats` precedent.
it('preserves the last known stats when a refresh fails', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()], stats: ['total' => 5, 'high_priority' => 2]);

    $screen = Native::test(RecommendationsOpenTab::class);

    expect($screen->get('stats'))->toBe(['total' => 5, 'high_priority' => 2]);

    fakeRecommendationsOpenEndpoints(error: 'boom');

    $screen->call('refresh');

    expect($screen->get('stats'))->toBe(['total' => 5, 'high_priority' => 2]);
});

it('filters by priority using the real chip binding', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()]);

    $screen = Native::test(RecommendationsOpenTab::class)
        ->assertElement('chip', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-priority-high' && ($n['props']['on_change'] ?? null) === callbackIdFor("setPriorityFilter('high')"));

    $screen->call('setPriorityFilter', 'high');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations?')
        && ! str_contains((string) $request->url(), '/recommendations/')
        && ($request['priority'] ?? null) === 'high');
});

// Behavioral counterpart: actually fire the chip's wire event by its ref (as
// a real device tap would — resolved through the node's own on_change prop)
// rather than calling setPriorityFilter() directly, and confirm it results
// in the correct API request. See StockProductsTabTest / StockAlertsTabTest
// for why targeting by ref (not by expression) is required to catch a
// chip mis-bound to @press instead of @change.
it('requests the high-priority filter when the "Haute" chip is tapped', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()]);

    Native::test(RecommendationsOpenTab::class)->toggle('reco-priority-high', true);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations?')
        && ! str_contains((string) $request->url(), '/recommendations/')
        && ($request['priority'] ?? null) === 'high');
});

// refresh() sends `'priority' => null` when the filter is 'all' (rather than
// omitting the key from the array outright). $request->data() reflects the
// raw pre-serialization array Http::get() was called with (so it still shows
// `priority: null` there) — the thing that actually matters is the request
// PHP sends over the wire, i.e. the serialized URL, and PHP's
// http_build_query() drops null-valued keys entirely rather than sending
// `priority=`. Assert against the URL, not $request->data(), to prove that.
it('omits the priority query param entirely when the filter is "all"', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()]);

    Native::test(RecommendationsOpenTab::class);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations')
        && ! str_contains((string) $request->url(), '/recommendations/')
        && ! str_contains((string) $request->url(), 'priority'));
});

it('gives a medium-priority recommendation its accent border color', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 2, 'priority' => 'medium', 'priority_label' => 'Moyenne']),
    ]);

    $screen = Native::test(RecommendationsOpenTab::class);

    $card = findNodeByRef($screen->tree(), 'reco-2-card');

    expect($card['style']['border_color'] ?? null)->toBe('#F59E0B');
});

it('gives a low-priority recommendation its accent border color', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 3, 'priority' => 'low', 'priority_label' => 'Basse']),
    ]);

    $screen = Native::test(RecommendationsOpenTab::class);

    $card = findNodeByRef($screen->tree(), 'reco-3-card');

    expect($card['style']['border_color'] ?? null)->toBe('#60A5FA');
});

it('marks a recommendation as done via the real button binding and removes it locally', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation(['id' => 7])]);

    $screen = Native::test(RecommendationsOpenTab::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-7-done' && ($n['props']['on_press'] ?? null) === callbackIdFor('markDone(7)'));

    $screen->call('markDone', 7);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations/7/done'));
    expect($screen->get('recommendations'))->toHaveCount(0);
});

it('rejects a recommendation via the real button binding and removes it locally', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation(['id' => 9])]);

    $screen = Native::test(RecommendationsOpenTab::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-9-reject' && ($n['props']['on_press'] ?? null) === callbackIdFor('reject(9)'));

    $screen->call('reject', 9);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations/9/reject'));
    expect($screen->get('recommendations'))->toHaveCount(0);
});

it('shows a NOUVEAU badge for recommendations created within the last 24 hours', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'created_at' => now()->subHours(2)->toIso8601String()]),
    ]);

    Native::test(RecommendationsOpenTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-new-badge');
});

it('does not show a NOUVEAU badge for older recommendations', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'created_at' => now()->subDays(3)->toIso8601String()]),
    ]);

    Native::test(RecommendationsOpenTab::class)
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-new-badge');
});

it('shows an empty state when there are no open recommendations', function () {
    fakeRecommendationsOpenEndpoints(recommendations: []);

    Native::test(RecommendationsOpenTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-open-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeRecommendationsOpenEndpoints(error: 'boom');

    Native::test(RecommendationsOpenTab::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-open-error');
});

it('is fully accessible', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()]);

    Native::test(RecommendationsOpenTab::class)->assertAccessible();
});

// Bug fix precedent (StockAlertsTab/StockProductsTab): <refreshable> was
// nested as one sibling among several inside the outer fill column, so it
// never claimed the remaining vertical space and the list didn't scroll. It
// must be the OUTERMOST element, wrapping the whole screen — stats, chip
// row, and the recommendation list.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation()]);

    $screen = Native::test(RecommendationsOpenTab::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-stats-total')
        ->assertElement('chip', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-priority-all')
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-done');
});
