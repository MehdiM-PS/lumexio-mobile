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

// The chart is one `webview` wire node carrying the whole chart document
// (Lumexio\NativeCharts) — bars, tooltip and bar selection are drawn by the
// engine inside it, so what PHP is still responsible for is the data it
// hands over.
it('renders the stock-history chart with one bar per history entry', function () {
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);

    $config = chartConfig(findNodeByRef($screen->tree(), 'stock-history-chart'));

    expect($config['type'])->toBe('bar');
    expect($config['unit'])->toBe(' en stock');

    // The API returns newest-first (14/08 then 13/08, per fakeStockHistory()); loadHistory()'s
    // array_reverse() must turn that into oldest-first (13/08 then 14/08) so the chart reads
    // chronologically left to right.
    expect($config['labels'])->toBe(['13/08', '14/08']);
    expect($config['series'])->toHaveCount(1);
    expect($config['series'][0]['data'])->toBe([12, 10]);

    // A single series has nothing to tell apart, so it carries no name and
    // the engine draws no legend for it.
    expect($config['series'][0])->not->toHaveKey('name');

    expect($screen->get('historyLabels'))->toBe(['13/08', '14/08']);
    expect($screen->get('historyQuantities'))->toBe([12, 10]);
});

it('hides the chart and does not crash when there is no stock history', function () {
    Http::fake(['*/products/stock-history*' => Http::response(['history' => []], 200)]);

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);

    $screen->assertMissingElement('webview')
        ->assertSee('T-shirt');

    expect($screen->get('historyQuantities'))->toBe([]);
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

// Nothing announces a web view's contents to a screen reader on its own, so
// both halves of the chart's accessibility come from here: the native node's
// `a11y_label`, and the `summary` the document exposes as the SVG's
// aria-label for when focus lands inside the web view instead.
it('labels the stock-history chart for screen readers, inside and out', function () {
    fakeStockHistory();

    $screen = Native::visit('/stock/item/product/1', data: ['item' => ['type' => 'product', 'id' => 1, 'name' => 'T-shirt', 'reference' => 'TS-1', 'quantity' => 10, 'low_stock_threshold' => 5, 'supplier_lead_time_days' => 7]]);
    $chart = findNodeByRef($screen->tree(), 'stock-history-chart');

    expect($chart['props']['a11y_label'] ?? null)->toBe('Historique de stock');
    expect(chartConfig($chart)['summary'])
        ->toContain('13/08')
        ->toContain('14/08')
        ->toContain('10 en stock')
        ->toContain('12 en stock');
});
