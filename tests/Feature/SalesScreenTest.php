<?php

use App\NativeComponents\Screens\Sales;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('renders a placeholder message', function () {
    Http::fake(['*/api/v1/auth/me' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']])]);

    $screen = Native::test(Sales::class);
    $tree = $screen->tree();

    expect($tree['type'] ?? null)->toBe('refreshable');
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'sales-placeholder' && ($n['props']['text'] ?? null) === 'Analyse des ventes — bientôt disponible');
});

it('opens the shop switcher sheet from the shared header trait', function () {
    Http::fake([
        '*/api/v1/auth/me' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']]),
        '*/api/v1/shops' => Http::response(['shops' => []]),
    ]);

    $screen = Native::test(Sales::class);
    $screen->call('openShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeTrue();
});
