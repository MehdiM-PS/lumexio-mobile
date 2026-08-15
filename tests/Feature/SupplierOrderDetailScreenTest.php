<?php

use App\NativeComponents\Screens\SupplierOrderDetail;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeSupplierOrderDetailEndpoint(array $order, ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/supplier-orders/*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake(['*/supplier-orders/*' => Http::response(['order' => $order], 200)]);
}

function sampleSupplierOrderDetail(array $overrides = []): array
{
    return array_merge([
        'id' => 'a1b2c3d4-0000-0000-0000-000000000001',
        'reference' => 'CF-20260815-0001',
        'name' => null,
        'status' => 'commandee',
        'status_label' => 'Commandée',
        'status_color' => '#3b82f6',
        'total_ht' => 450.0,
        'expected_delivery_date' => '2026-08-25',
        'delivered_at' => null,
        'notes' => null,
        'supplier' => ['id' => 1, 'name' => 'Fournisseur Textile SA'],
        'allowed_transitions' => ['en_cours', 'recue_partielle', 'recue', 'annulee'],
        'created_at' => now()->toIso8601String(),
    ], [
        'items' => [
            ['id' => 1, 'supplier_reference' => 'REF-1', 'quantity' => 10, 'unit_cost' => 5.0, 'total_cost' => 50.0, 'received_quantity' => 0, 'product_id' => 1, 'product_variant_id' => null, 'product_name' => 'T-shirt bleu', 'product_reference' => 'TS-BLUE', 'variant_name' => null, 'variant_reference' => null],
        ],
        'status_histories' => [
            ['id' => 1, 'from_status' => null, 'to_status' => 'brouillon', 'user' => ['id' => 1, 'name' => 'Jean'], 'created_at' => now()->toIso8601String()],
        ],
        'margin' => ['total_revenue_ht' => 80.0, 'margin_euros' => 30.0, 'margin_percent' => 37.5, 'has_partial_prices' => false],
    ], $overrides);
}

it('renders an inline top bar with the reference and a back button, hoisted into native chrome', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail());

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertNavTitle('CF-20260815-0001')
        ->assertElement('native_root_stack', fn (array $n): bool => ($n['props']['back'] ?? null) === true);
});

it('paints instantly from navigation data then hydrates line items via loadDetail', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789']));

    $screen = Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])]);

    expect($screen->get('order')['items'])->toHaveCount(1);
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-item-1-name' && ($n['props']['text'] ?? null) === 'T-shirt bleu');
});

it('transitions the order via the real button binding and refetches', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789', 'allowed_transitions' => ['en_cours', 'annulee']]));

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-transition-en_cours' && ($n['props']['on_press'] ?? null) === callbackIdFor("transitionTo('en_cours')"))
        ->call('transitionTo', 'en_cours');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/transition') && ($request['status'] ?? null) === 'en_cours');
});

it('does not show a transition button for recue, the receive-only status', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789', 'allowed_transitions' => ['recue', 'annulee']]));

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertMissingElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-transition-recue')
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-transition-annulee');
});

it('shows the receive button only when status is pending, and marks fully received via the real binding', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789', 'status' => 'commandee']));

    $screen = Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789', 'status' => 'commandee'])])
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-receive' && ($n['props']['on_press'] ?? null) === callbackIdFor('markFullyReceived'));

    $screen->call('markFullyReceived');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/receive') && ($request['received_quantities'][1] ?? null) === 10);
});

it('does not show the receive button when status is brouillon', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789', 'status' => 'brouillon']));

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789', 'status' => 'brouillon'])])
        ->assertMissingElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-receive')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-reference');
});

it('duplicates the order via the real button binding and navigates to the new order', function () {
    Http::fake([
        '*/supplier-orders/xyz-789/duplicate' => Http::response(['order' => sampleSupplierOrderDetail(['id' => 'new-order-999'])], 201),
        '*/supplier-orders/*' => Http::response(['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])], 200),
    ]);

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-duplicate' && ($n['props']['on_press'] ?? null) === callbackIdFor('duplicate'))
        ->call('duplicate')
        ->assertNavigatedTo('/supplier-orders/new-order-999');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeSupplierOrderDetailEndpoint([], error: 'boom');

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'so-detail-error');
});

it('wraps its content in a refreshable element wired to loadDetail', function () {
    fakeSupplierOrderDetailEndpoint(sampleSupplierOrderDetail(['id' => 'xyz-789']));

    Native::test(SupplierOrderDetail::class, params: ['id' => 'xyz-789'], data: ['order' => sampleSupplierOrderDetail(['id' => 'xyz-789'])])
        ->assertElement('refreshable', fn (array $n): bool => ($n['props']['on_refresh'] ?? null) === callbackIdFor('loadDetail'));
});
