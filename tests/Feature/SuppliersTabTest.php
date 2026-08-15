<?php

use App\NativeComponents\SuppliersTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeSuppliersEndpoints(array $suppliers = [], ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/suppliers*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/suppliers*' => Http::response([
            'suppliers' => ['data' => $suppliers, 'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => count($suppliers)]],
        ], 200),
    ]);
}

function sampleSupplier(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'Fournisseur Textile SA',
        'contact_name' => 'Marie Dubois',
        'email' => 'marie@textile-sa.fr',
        'phone' => '0102030405',
        'address' => '12 rue de la Mode, Paris',
        'notes' => null,
        'active_orders_count' => 2,
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}

it('shows the supplier list', function () {
    fakeSuppliersEndpoints(suppliers: [sampleSupplier()]);

    Native::test(SuppliersTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'supplier-1-name' && ($n['props']['text'] ?? null) === 'Fournisseur Textile SA');
});

it('re-fetches with the search term', function () {
    fakeSuppliersEndpoints(suppliers: [sampleSupplier()]);

    $screen = Native::test(SuppliersTab::class);
    $screen->set('search', 'textile');
    $screen->call('refresh');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/suppliers?') && ($request['search'] ?? null) === 'textile');
});

it('navigates to the supplier form when a row is selected, looking up the supplier by id', function () {
    fakeSuppliersEndpoints(suppliers: [sampleSupplier(['id' => 1])]);

    Native::test(SuppliersTab::class)
        ->assertElement('pressable', fn (array $n): bool => ($n['ref'] ?? null) === 'supplier-1' && ($n['on_press'] ?? null) === callbackIdFor('select(1)'))
        ->call('select', 1)
        ->assertNavigatedTo('/suppliers/1');
});

it('navigates to the create form when the add button is pressed', function () {
    fakeSuppliersEndpoints(suppliers: []);

    Native::test(SuppliersTab::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'suppliers-add' && ($n['props']['on_press'] ?? null) === callbackIdFor('goToCreate'))
        ->call('goToCreate')
        ->assertNavigatedTo('/suppliers/create');
});

it('shows an empty state when there are no suppliers', function () {
    fakeSuppliersEndpoints(suppliers: []);

    Native::test(SuppliersTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'suppliers-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeSuppliersEndpoints(error: 'boom');

    Native::test(SuppliersTab::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'suppliers-error');
});

it('wires pull-to-refresh to the refresh method', function () {
    fakeSuppliersEndpoints(suppliers: [sampleSupplier()]);

    Native::test(SuppliersTab::class)
        ->assertElement('refreshable', fn (array $n): bool => ($n['props']['on_refresh'] ?? null) === callbackIdFor('refresh'));
});
