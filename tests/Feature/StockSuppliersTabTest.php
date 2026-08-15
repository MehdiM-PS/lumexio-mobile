<?php

use App\NativeComponents\StockSuppliersTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeStockSuppliersTabEndpoints(): void
{
    Http::fake([
        '*/supplier-orders*' => Http::response([
            'orders' => ['data' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]],
            'stats' => ['total' => 0, 'pending' => 0, 'pending_value' => 0.0, 'received_month' => 0],
        ], 200),
        '*/suppliers*' => Http::response([
            'suppliers' => ['data' => [], 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]],
        ], 200),
    ]);
}

it('shows the inner tab-row with two sub-tabs, Commandes first', function () {
    fakeStockSuppliersTabEndpoints();

    Native::test(StockSuppliersTab::class)
        ->assertElement('tab_row', fn (array $n): bool => ($n['ref'] ?? null) === 'stock-suppliers-subtabs'
            && ($n['props']['on_change'] ?? null) === callbackIdFor("__syncProperty('activeSubTab')"))
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Commandes')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Annuaire');

    expect((new StockSuppliersTab)->activeSubTab)->toBe(0);
});

it('shows the supplier orders tab by default and switches to the directory via a real tab-change event', function () {
    fakeStockSuppliersTabEndpoints();

    $screen = Native::test(StockSuppliersTab::class);

    expect($screen->get('activeSubTab'))->toBe(0);
    $screen->assertSee('Aucune commande fournisseur trouvée.')
        ->assertDontSee('Aucun fournisseur trouvé.');

    // Dispatch a genuine EVENT_TAB_CHANGE at the tab-row's own callback id
    // (resolved from the published tree), not a direct property set — this
    // is what proves the native:model binding actually wired up inside a
    // CHILD component's render scope, not just that the @if reacts to PHP
    // state changing.
    $screen->changeTab('stock-suppliers-subtabs', 1);

    expect($screen->get('activeSubTab'))->toBe(1);
    $screen->assertSee('Aucun fournisseur trouvé.')
        ->assertDontSee('Aucune commande fournisseur trouvée.');
});
