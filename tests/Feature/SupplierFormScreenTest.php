<?php

use App\NativeComponents\Screens\SupplierForm;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('creates a new supplier and navigates back', function () {
    Http::fake(['*/suppliers' => Http::response(['supplier' => ['id' => 9, 'name' => 'Nouveau Fournisseur']], 201)]);

    $screen = Native::test(SupplierForm::class);
    $screen->set('name', 'Nouveau Fournisseur');
    $screen->set('email', 'contact@nouveau.fr');
    $screen->call('submit');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains((string) $request->url(), '/suppliers')
        && ($request['name'] ?? null) === 'Nouveau Fournisseur');
    $screen->assertWentBack();
});

it('prefills the form in edit mode from navigation data', function () {
    $screen = Native::test(SupplierForm::class, params: ['id' => 3], data: ['supplier' => [
        'id' => 3, 'name' => 'Fournisseur Existant', 'contact_name' => 'Paul', 'email' => 'paul@existant.fr', 'phone' => '0600000000', 'address' => '1 rue Test', 'notes' => 'RAS',
    ]]);

    expect($screen->get('name'))->toBe('Fournisseur Existant');
    expect($screen->get('email'))->toBe('paul@existant.fr');
});

it('updates an existing supplier via PATCH and navigates back', function () {
    Http::fake(['*/suppliers/3' => Http::response(['supplier' => ['id' => 3, 'name' => 'Fournisseur Modifié']], 200)]);

    $screen = Native::test(SupplierForm::class, params: ['id' => 3], data: ['supplier' => ['id' => 3, 'name' => 'Fournisseur Existant', 'contact_name' => null, 'email' => null, 'phone' => null, 'address' => null, 'notes' => null]]);
    $screen->set('name', 'Fournisseur Modifié');
    $screen->call('submit');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains((string) $request->url(), '/suppliers/3'));
    $screen->assertWentBack();
});

it('shows a validation error and does not navigate away on failure', function () {
    Http::fake(['*/suppliers' => Http::response(['message' => 'Le nom est requis.', 'errors' => ['name' => ['Le nom est requis.']]], 422)]);

    $screen = Native::test(SupplierForm::class);
    $screen->call('submit');

    $screen->assertNoNavigation();
    $screen->assertSee('Le nom est requis.');
});

it('is fully accessible', function () {
    Native::test(SupplierForm::class)->assertAccessible();
});
