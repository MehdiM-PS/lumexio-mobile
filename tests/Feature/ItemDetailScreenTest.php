<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeStockHistory(): void
{
    Http::fake(['*/products/stock-history*' => Http::response([
        'history' => [
            ['date' => '2026-08-14', 'date_formatted' => '14/08', 'quantity' => 10, 'quantity_sold' => 2, 'variation' => -2],
            ['date' => '2026-08-13', 'date_formatted' => '13/08', 'quantity' => 12, 'quantity_sold' => 1, 'variation' => -1],
        ],
    ], 200)]);
}

/** Recursively count wire-tree nodes of a given type — no built-in "count" assertion exists on TestableComponent. */
function countElementsOfType(array $node, string $type): int
{
    $count = ($node['type'] ?? null) === $type ? 1 : 0;

    foreach ($node['children'] ?? [] as $child) {
        $count += countElementsOfType($child, $type);
    }

    return $count;
}

it('shows the product passed via navigation data, including edit controls', function () {
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: [
        'item' => [
            'type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1',
            'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7,
            'supplier_name' => 'Acme', 'category_name' => 'Vêtements',
            'recommended_reorder_quantity' => 20, 'recommended_reorder_date' => '2026-08-20',
        ],
    ])
        ->assertSee('T-shirt')
        ->assertSee('Acme')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'threshold-input')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'lead-time-input');
});

it('hides edit controls for a variant', function () {
    fakeStockHistory();

    Native::visit('/stock/item/variant/1', data: [
        'item' => [
            'type' => 'variant', 'id' => 1, 'name' => 'T-shirt - Rouge', 'reference' => 'TS-1-R',
            'quantity' => 4, 'low_stock_threshold' => 5, 'supplier_name' => 'Acme', 'category_name' => 'Vêtements',
        ],
    ])
        ->assertSee('T-shirt - Rouge')
        ->assertMissingElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'threshold-input')
        ->assertMissingElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'lead-time-input')
        // ref-based rather than a text-substring assertSee(): the variant's own name
        // ("T-shirt - Rouge") already contains "T-shirt", so any assertSee() built from
        // a fragment of it risks the exact substring-collision bug found in Task 2
        // (StockProductsTabTest) — asserting on the note's own `ref` sidesteps that.
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'variant-readonly-note')
        ->assertSee("n'est pas disponible pour les déclinaisons");
});

it('saves a new threshold', function () {
    fakeStockHistory();
    Http::fake(['*/products/1/threshold' => Http::response(['message' => 'Seuil mis à jour', 'product' => ['low_stock_threshold' => 8]], 200)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->set('thresholdInput', '8')
        ->call('saveThreshold');

    // Not ->assertSet('item.low_stock_threshold', 8): TestableComponent::get() does a
    // literal `$this->component->{$property}` lookup (no dot-path/data_get support), so
    // a dotted property name would silently resolve to null via NativeComponent::__get()
    // and always fail regardless of implementation correctness. Read the array property
    // directly instead.
    expect($screen->get('item')['low_stock_threshold'])->toBe(8);
});

it('shows a generic error and keeps the prior value when saving the threshold fails', function () {
    fakeStockHistory();
    Http::fake(['*/products/1/threshold' => Http::response(['message' => 'Erreur de validation.', 'errors' => ['threshold' => ['Invalide.']]], 422)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->set('thresholdInput', '8')
        ->call('saveThreshold');

    expect($screen->get('item')['low_stock_threshold'])->toBe(5);
    $screen->assertSee('Invalide.');
    // The `item` assertion above holds even if the input revert never runs (the item was
    // never mutated on failure either way) — assert the input itself reverted too, which is
    // what ItemDetail::saveThreshold()'s else branch actually does.
    $screen->assertSet('thresholdInput', '5');
});

it('saves a new supplier lead time', function () {
    fakeStockHistory();
    Http::fake(['*/products/1/lead-time' => Http::response(['message' => 'Délai mis à jour', 'product' => ['supplier_lead_time_days' => 14]], 200)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->set('leadTimeInput', '14')
        ->call('saveLeadTime');

    expect($screen->get('item')['supplier_lead_time_days'])->toBe(14);
});

it('shows a generic error and keeps the prior value when saving the lead time fails', function () {
    fakeStockHistory();
    Http::fake(['*/products/1/lead-time' => Http::response(['message' => 'Erreur de validation.', 'errors' => ['supplier_lead_time_days' => ['Invalide.']]], 422)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->set('leadTimeInput', '14')
        ->call('saveLeadTime');

    expect($screen->get('item')['supplier_lead_time_days'])->toBe(7);
    $screen->assertSee('Invalide.');
    $screen->assertSet('leadTimeInput', '7');
});

it('renders the stock-history chart as a bar per history entry', function () {
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);

    $screen->assertElement('canvas')
        ->assertElement('rect');

    // fakeStockHistory() returns 2 days of history — exactly one <rect> per day.
    expect(countElementsOfType($screen->tree(), 'rect'))->toBe(2);

    // The API returns newest-first (14/08 then 13/08, per fakeStockHistory()); loadHistory()'s
    // array_reverse() must turn that into oldest-first (13/08 then 14/08) so the view's
    // left-to-right @foreach draws a chronological chart.
    expect($screen->get('historyLabels'))->toBe(['13/08', '14/08']);
    expect($screen->get('historyQuantities'))->toBe([12, 10]);
});

it('hides the chart and does not crash when there is no stock history', function () {
    Http::fake(['*/products/stock-history*' => Http::response(['history' => []], 200)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);

    $screen->assertMissingElement('canvas')
        ->assertMissingElement('rect')
        ->assertSee('T-shirt');

    expect($screen->get('historyQuantities'))->toBe([]);

    // The view's `@if (count($historyQuantities) > 0)` gate never calls barHeight() in this
    // state, so it alone doesn't prove barHeight()'s own guard is safe. Call it directly:
    // `$this->historyQuantities ?: [1]` must keep max() from receiving an empty array (a PHP
    // ValueError) when historyQuantities is [].
    expect($screen->instance()->barHeight(5))->toBeInt();
});

it('is fully accessible', function () {
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->assertAccessible();
});

// Bug 1 + 2 fix: an inline <top-bar> (no NativeLayout — this screen is a
// top-level route outside TabsLayout's nativeGroup) gives the screen the
// real NavigationStack-backed chrome: automatic top safe-area handling
// AND a back chevron. The inline bar is hoisted out of the content tree
// into the `native_root_stack` sentinel's own props (NOT rendered as a
// `top_bar` element in-tree) — see NativeComponent::wrapWithChrome()/
// wrapWithNativeChrome() in vendor/nativephp/mobile.
//
// `back` is explicit (not left to "pushed screens get it for free"):
// ItemDetail publishes its OWN `native_root_stack`, separate from the
// tab it's pushed from (Stock renders via `native_root_tabs` +
// PerTabNavigationCoordinator, a different Swift coordinator). Per
// NavigationCoordinator.swift, that stack's `rootUri` is seeded from
// the FIRST uri it ever sees — which is ItemDetail's own URI, making it
// `isRoot: true` on ITS stack even though PHP's router considers it
// pushed. NativeRootStackRenderer.swift only draws a manual chevron
// when `showBack && isRoot`, so without an explicit `back` this screen
// would have no way out. (Verified via source inspection only — no iOS
// simulator available in this environment.)
it('renders an inline top bar with the item name as the title and a back button, hoisted into native chrome', function () {
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->assertNavTitle('T-shirt')
        ->assertElement('native_root_stack', fn (array $n): bool => ($n['props']['title'] ?? null) === 'T-shirt' && ($n['props']['back'] ?? null) === true)
        // Hoisted, not drawn in-tree.
        ->assertMissingElement('top_bar');
});

// Bug 3 fix: pull-to-refresh, wired to loadHistory() — the only genuinely
// re-fetchable data on this screen (the item's own base fields come from
// navigation data, not a live fetch).
it('wraps its content in a refreshable element wired to loadHistory', function () {
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        // Refreshable::resolveProps() registers the bound method as
        // props.on_refresh (a numeric, content-addressed callback id).
        // Comparing against callbackIdFor('loadHistory') — rather than
        // just isset() — proves it's bound to THIS method specifically;
        // isset() alone would still pass for a typo like "loadHistry".
        // loadHistory() always busts the short-lived GET cache before
        // reloading, so pull-to-refresh actually re-hits the network.
        ->assertElement('refreshable', fn (array $n): bool => ($n['props']['on_refresh'] ?? null) === callbackIdFor('loadHistory'));
});

it('refetches stock history on pull-to-refresh', function () {
    Http::fake(['*/products/stock-history*' => Http::sequence()
        ->push(['history' => [
            ['date' => '2026-08-14', 'date_formatted' => '14/08', 'quantity' => 10, 'quantity_sold' => 2, 'variation' => -2],
        ]], 200)
        ->push(['history' => [
            ['date' => '2026-08-14', 'date_formatted' => '14/08', 'quantity' => 25, 'quantity_sold' => 2, 'variation' => -2],
        ]], 200),
    ]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);

    expect($screen->get('historyQuantities'))->toBe([10]);

    // The refreshable's @refresh handler calls this same method, which
    // always busts the cache before reloading — this proves a second call
    // re-fetches rather than reusing cached data.
    $screen->call('loadHistory');

    expect($screen->get('historyQuantities'))->toBe([25]);
});

it('does not show the stock-history chart tooltip before any bar is tapped', function () {
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-tooltip');
});

it('shows the tapped bar\'s date and quantity in the stock-history chart tooltip', function () {
    // fakeStockHistory() returns newest-first (14/08 then 13/08); loadHistory()'s
    // array_reverse() makes index 0 the oldest entry: 13/08, quantity 12.
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);
    $screen->call('selectBar', 0);

    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-tooltip'
        && ($n['props']['text'] ?? null) === '13/08 — 12 en stock');
});

it('wires each stock-history bar to selectBar with its own baked-in index', function () {
    // Element::toArray() registers `@press`'s pressMethod as a top-level
    // `on_press` field regardless of whether the element type actually
    // reads it (the same trap documented for Chip's `@press`/on_change
    // mismatch elsewhere in this suite) — so this only proves something if
    // the id is compared against the exact per-bar expression, not just
    // isset(). It also proves the index is genuinely baked per-bar: a view
    // where every rect carried `@press="selectBar(0)"` would still pass an
    // isset()-only check but fails this one, since bar-1's id would then
    // equal callbackIdFor('selectBar(0)') instead of ('selectBar(1)').
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-bar-0'
            && ($n['on_press'] ?? null) === callbackIdFor('selectBar(0)'))
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-bar-1'
            && ($n['on_press'] ?? null) === callbackIdFor('selectBar(1)'));
});

it('gives each stock-history bar an a11y-label announcing its date and quantity', function () {
    // assertAccessible() has no audit rule for the `rect` type (verified by
    // reading TestableComponent::collectA11yViolations()), so it would stay
    // green even if these labels were missing entirely — this asserts the
    // resolved `a11y_label` prop directly instead, on both bars, so a
    // regression that drops or misindexes the label is actually caught.
    // historyLabels/historyQuantities are oldest-first after loadHistory()'s
    // array_reverse(): index 0 is 13/08 (qty 12), index 1 is 14/08 (qty 10).
    fakeStockHistory();

    Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]])
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-bar-0'
            && ($n['props']['a11y_label'] ?? null) === '13/08 — 12 en stock')
        ->assertElement('rect', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-bar-1'
            && ($n['props']['a11y_label'] ?? null) === '14/08 — 10 en stock');
});

it('deselects the stock-history bar and hides the tooltip when tapped a second time', function () {
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);
    $screen->call('selectBar', 0);

    // Confirm the tooltip is genuinely showing between the two taps, so this
    // test fails if selectBar() is broken outright (not only if the toggle-
    // off branch specifically regresses).
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-tooltip');

    $screen->call('selectBar', 0);

    expect($screen->get('selectedBarIndex'))->toBeNull();
    $screen->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-history-chart-tooltip');
});

it('gives the selected stock-history bar a different style than an unselected bar', function () {
    // Before any tap all bars share the same (unselected) style; after
    // selecting bar 0 only that bar's background should change — proves
    // the conditional class is actually wired to $selectedBarIndex rather
    // than always-on or always-off.
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);
    $before = $screen->tree();
    $bar0Before = findNodeByRef($before, 'stock-history-chart-bar-0');
    $bar1Before = findNodeByRef($before, 'stock-history-chart-bar-1');

    expect($bar0Before)->not->toBeNull();
    expect($bar1Before)->not->toBeNull();
    expect($bar0Before['style']['bg_color'] ?? null)->toBe($bar1Before['style']['bg_color'] ?? null);

    $screen->call('selectBar', 0);

    $after = $screen->tree();
    $bar0After = findNodeByRef($after, 'stock-history-chart-bar-0');
    $bar1After = findNodeByRef($after, 'stock-history-chart-bar-1');

    expect($bar0After['style']['bg_color'] ?? null)
        ->not->toBe($bar1After['style']['bg_color'] ?? null)
        ->not->toBe($bar0Before['style']['bg_color'] ?? null);
});

it('resets the selected bar index when stock history is reloaded', function () {
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);
    $screen->call('selectBar', 0);

    expect($screen->get('selectedBarIndex'))->toBe(0);

    $screen->call('loadHistory');

    expect($screen->get('selectedBarIndex'))->toBeNull();
});
