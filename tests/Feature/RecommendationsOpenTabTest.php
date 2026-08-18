<?php

use App\NativeComponents\RecommendationsOpenTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeRecommendationsOpenEndpoints(array $recommendations = [], ?array $stats = null, ?string $error = null): void
{
    // A few of this file's tests drive the screen through Native::visit(),
    // which (unlike Native::test()) renders the full Screens\Recommendations
    // component — it mixes in HasHeaderChrome and calls loadCurrentUser() on
    // mount. Left unfaked, that /auth/me request is a stray request (see
    // RecommendationsScreenTest's fakeRecommendationsScreenEndpoints()).
    // Harmless for the Native::test()-only tests in this file, which never
    // render the header chrome and so never hit this endpoint.
    if ($error !== null) {
        Http::fake([
            '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']], 200),
            '*/recommendations*' => Http::response(['message' => $error], 500),
        ]);

        return;
    }

    Http::fake([
        '*/auth/me*' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']], 200),
        '*/recommendations/*/done' => Http::response(['message' => 'Recommandation marquée comme traitée'], 200),
        '*/recommendations/*/reject' => Http::response(['message' => 'Recommandation rejetée'], 200),
        '*/recommendations*' => Http::response([
            'recommendations' => $recommendations,
            'stats' => $stats ?? ['total' => count($recommendations), 'high_priority' => 0],
            'available_types' => [],
        ], 200),
    ]);
}

// sampleRecommendation() is now shared in tests/Pest.php (alongside
// callbackIdFor()/findNodeByRef()) so both this file and
// RecommendationsActionedTabTest.php can rely on it even when run directly
// without --filter.

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
//
// Deliberately does NOT use fakeRecommendationsOpenEndpoints() twice for the
// two responses: Http::fake() stubs are cumulative, not replaced — calling
// it a second time with a NEW rule for the same '*/recommendations*'
// pattern does not override the first rule, and PendingRequest's stub
// handler resolves to the FIRST matching rule. Two separate
// fakeRecommendationsOpenEndpoints() calls here would silently keep hitting
// the original success response on the second refresh() too, making the
// "failure" branch never actually exercised (same class of bug as Orders'
// Task 3 double-Http::fake() bug). Http::fakeSequence() registers ONE rule
// for the URL pattern that serves its pushed responses in order, so the
// second refresh() genuinely hits the 500.
it('preserves the last known stats when a refresh fails', function () {
    Http::fake([
        '*/recommendations*' => Http::sequence()
            ->push(['recommendations' => [sampleRecommendation()], 'stats' => ['total' => 5, 'high_priority' => 2], 'available_types' => []], 200)
            ->push(['message' => 'boom'], 500),
    ]);

    $screen = Native::test(RecommendationsOpenTab::class);

    expect($screen->get('stats'))->toBe(['total' => 5, 'high_priority' => 2]);

    $screen->call('refresh');

    expect($screen->get('lastApiError'))->not->toBeNull();
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

it('does not group recommendations under headings', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'group' => 'alerts', 'group_label' => 'Alertes']),
        sampleRecommendation(['id' => 2, 'group' => 'trends', 'group_label' => 'Tendances']),
    ]);

    Native::test(RecommendationsOpenTab::class)
        ->assertMissingElement('text', fn (array $n): bool => str_starts_with($n['ref'] ?? '', 'reco-group-'));
});

it('shows a priority badge colored by priority, using the same label vocabulary as the filter chips (not priority_label)', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'priority' => 'high', 'priority_label' => 'Urgent']),
        sampleRecommendation(['id' => 2, 'priority' => 'medium', 'priority_label' => 'Recommandé']),
        sampleRecommendation(['id' => 3, 'priority' => 'low', 'priority_label' => 'Suggestion']),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();

    $high = findNodeByRef($tree, 'reco-1-priority-badge');
    $medium = findNodeByRef($tree, 'reco-2-priority-badge');
    $low = findNodeByRef($tree, 'reco-3-priority-badge');

    expect($high['props']['variant'] ?? null)->toBe('destructive');
    expect($high['props']['label'] ?? null)->toBe('Haute');
    expect($medium['props']['variant'] ?? null)->toBe('accent');
    expect($medium['props']['label'] ?? null)->toBe('Moyenne');
    expect($low['props']['variant'] ?? null)->toBe('primary');
    expect($low['props']['label'] ?? null)->toBe('Basse');
});

it('shows the type as its own badge-style pill next to the priority badge', function () {
    // Mock (line 339): type is a second pill, not text merged with
    // freshness via " · ".
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'type_label' => 'Price opportunity', 'freshness_label' => 'Nouveau']),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();
    $type = findNodeByRef($tree, 'reco-1-type');

    expect($type['props']['text'] ?? null)->toBe('Price opportunity');
    // No other <text> in this codebase carries a bg-* class before this —
    // every existing background sits on a column/row/pressable — so this
    // proves a text node actually paints its own pill background rather
    // than the class silently dropping.
    expect($type['style']['bg_color'] ?? null)->toBe(theme('surface-variant'));
});

it('shows the freshness label in the card footer next to the action buttons', function () {
    // Mock (line 369): activeSince lives in the footer, not merged with
    // the type label up top.
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'freshness_label' => 'Active depuis 3 jours']),
    ]);

    Native::test(RecommendationsOpenTab::class)->assertSee('Active depuis 3 jours');
});

it('shows the product reference chip prefixed with "Réf :" when ref is present', function () {
    // Mock (line 342): "Réf : {{r.ref}}", not the bare ref.
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'ref' => 'SKU-CHIP-7']),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();
    $ref = findNodeByRef($tree, 'reco-1-ref');

    expect($ref['props']['text'] ?? null)->toBe('Réf : SKU-CHIP-7');
    expect($ref['style']['bg_color'] ?? null)->toBe(theme('surface-variant'));
});

it('hides the product reference chip when ref is null', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'ref' => null]),
    ]);

    Native::test(RecommendationsOpenTab::class)
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-ref');
});

it('shows the actions list when actions are present', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'actions' => ['Baisser le prix de 10%', 'Relancer la campagne email']]),
    ]);

    Native::visit('/recommendations')
        ->assertSee('Baisser le prix de 10%')
        ->assertSee('Relancer la campagne email');
});

it('marks each action with a bolt icon, not the mock\'s raw "⚡" emoji or a plain bullet', function () {
    // CLAUDE.md forbids raw emoji in UI text; "•" was the first-pass
    // stand-in, but a lightning bolt has a direct native:icon equivalent so
    // that's used instead. Uses the plain shared `name="bolt.fill"` string
    // (like every other icon in this codebase), NOT `:ios`/`:android`
    // overrides — those only resolve when Platform::current() detects a
    // live device over the bridge, which never happens under Pest, so an
    // override-only icon would resolve with no `name` prop at all here
    // (verified empirically before choosing the shared-string form).
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'actions' => ['Baisser le prix de 10%']]),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();
    $row = findNodeByRef($tree, 'reco-1-action-0');

    expect($row)->not->toBeNull();
    $icon = collect($row['children'] ?? [])->firstWhere('type', 'icon');
    expect($icon)->not->toBeNull();
    expect($icon['props']['name'] ?? null)->toBe('bolt.fill');
});

it('omits the actions section when actions is empty', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'actions' => []]),
    ]);

    // Mock (line 358): the literal copy is "ACTIONS RECOMMANDÉES" (upper case).
    Native::test(RecommendationsOpenTab::class)
        ->assertMissingElement('text', fn (array $n): bool => ($n['props']['text'] ?? null) === 'ACTIONS RECOMMANDÉES');
});

it('renders data fields as a two-column metrics grid', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'data_fields' => [
            ['label' => 'Produit', 'value' => 'T-shirt bleu'],
            ['label' => 'Stock', 'value' => '2 unités'],
            ['label' => 'Ventes/jour', 'value' => '5'],
        ]]),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();
    $metrics = findNodeByRef($tree, 'reco-1-metrics');

    expect($metrics['children'])->toHaveCount(2);
    expect($metrics['children'][0]['children'])->toHaveCount(2);
    expect($metrics['children'][1]['children'])->toHaveCount(1);
});

it('labels the action buttons Effectuée and Pas intéressé with icons', function () {
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation(['id' => 1])]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();

    $done = findNodeByRef($tree, 'reco-1-done');
    $reject = findNodeByRef($tree, 'reco-1-reject');

    expect($done['props']['label'] ?? null)->toBe('Effectuée');
    expect($done['props']['leading_icon'] ?? null)->toBe('checkmark');
    expect($reject['props']['label'] ?? null)->toBe('Pas intéressé');
    expect($reject['props']['leading_icon'] ?? null)->toBe('xmark');
});

it('orders the action buttons Pas intéressé before Effectuée, matching the mock', function () {
    // Mock (line 371-372): [Pas intéressé] then [Effectuée] — reject comes
    // first, unlike this file's own -done/-reject ref naming order.
    fakeRecommendationsOpenEndpoints(recommendations: [sampleRecommendation(['id' => 1])]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();
    $buttons = collect([]);
    $walk = function (array $n) use (&$walk, &$buttons) {
        if ($n['type'] === 'button') {
            $buttons->push($n['ref'] ?? null);
        }
        foreach ($n['children'] ?? [] as $c) {
            $walk($c);
        }
    };
    $walk($tree);

    expect($buttons->values()->all())->toBe(['reco-1-reject', 'reco-1-done']);
});

it('colors each recommendation card border by priority, not a flat neutral outline', function () {
    // Mock's priorityMeta (line 722-726): border color tracks priority at
    // the same destructive/accent/primary mapping as the badge.
    fakeRecommendationsOpenEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'priority' => 'high']),
        sampleRecommendation(['id' => 2, 'priority' => 'medium']),
        sampleRecommendation(['id' => 3, 'priority' => 'low']),
    ]);

    $tree = Native::test(RecommendationsOpenTab::class)->tree();

    $high = findNodeByRef($tree, 'reco-1-card');
    $medium = findNodeByRef($tree, 'reco-2-card');
    $low = findNodeByRef($tree, 'reco-3-card');

    $borderColor = fn (array $n) => $n['style']['border_color'] ?? null;

    expect($borderColor($high))->not->toBeNull();
    expect($borderColor($high))->not->toBe($borderColor($medium));
    expect($borderColor($medium))->not->toBe($borderColor($low));
});
