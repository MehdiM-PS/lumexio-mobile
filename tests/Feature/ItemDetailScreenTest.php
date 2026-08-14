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
