<?php

use App\NativeComponents\Screens\Sales;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeSalesEndpoints(array $overrides = [], ?string $error = null): void
{
    if ($error !== null) {
        Http::fake([
            '*/auth/me' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']]),
            '*/revenue*' => Http::response(['message' => $error], 500),
        ]);

        return;
    }

    $defaults = [
        'metrics' => [
            'current' => ['revenue' => 89400.0, 'orders' => 612, 'avg_order' => 146.0, 'new_customers' => 89],
            'previous' => ['revenue' => 79800.0, 'orders' => 567, 'avg_order' => 141.0, 'new_customers' => 77],
            'changes' => ['revenue' => 12.0, 'orders' => 8.0, 'avg_order' => 3.0, 'new_customers' => 15.0],
        ],
        'top_products' => [
            ['product_id' => 1, 'name' => 'Sneakers Runner Pro', 'sales' => 214, 'revenue' => 14200.0],
            ['product_id' => 2, 'name' => 'Jean Slim Bleu', 'sales' => 168, 'revenue' => 9800.0],
        ],
        'charts' => [
            'revenue_margin' => ['labels' => ['S1', 'S2', 'S3'], 'revenue' => [100.0, 200.0, 300.0], 'margin' => [10.0, 20.0, 30.0]],
            'avg_cart' => ['labels' => ['S1', 'S2', 'S3'], 'avgCart' => [50.0, 60.0, 70.0], 'groupBy' => 'daily'],
            'category_breakdown' => [
                ['label' => 'Chaussures', 'amount' => 34200.0, 'pct' => 38],
                ['label' => 'Vêtements', 'amount' => 27900.0, 'pct' => 31],
            ],
        ],
    ];

    Http::fake([
        '*/auth/me' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']]),
        '*/revenue*' => Http::response(array_replace_recursive($defaults, $overrides), 200),
    ]);
}

it('shows the CA & Ventes title', function () {
    fakeSalesEndpoints();

    Native::visit('/sales')->assertSee('CA & Ventes');
});

it('sends the correct period key for each pill', function ($key) {
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', $key);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/revenue')
        && ($request['period'] ?? null) === $key);
})->with(['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'year', 'last_year']);

it('shows the metrics grid with current values and colored deltas', function () {
    fakeSalesEndpoints();

    Native::visit('/sales')
        ->assertSee('89 400,00 €')
        ->assertSee('612')
        ->assertSee('146,00 €')
        ->assertSee('89')
        ->assertSee('+12%');
});

it('shows the comparison table by default (compareEnabled starts true)', function () {
    fakeSalesEndpoints();

    Native::visit('/sales')
        ->assertSee('Actuelle vs période -1')
        ->assertSee('79 800,00 €');
});

it('hides the comparison table when the compare toggle is off', function () {
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->set('compareEnabled', false);

    $screen->assertDontSee('Actuelle vs période -1');
});

it('shows the category breakdown rows', function () {
    fakeSalesEndpoints();

    Native::visit('/sales')
        ->assertSee('Chaussures')
        ->assertSee('34 200,00 €')
        ->assertSee('38%');
});

it('slices top products to 5 even when the API returns more', function () {
    $topTen = collect(range(1, 10))->map(fn ($i) => [
        'product_id' => $i, 'name' => "Product $i", 'sales' => 100 - $i, 'revenue' => (1000 - $i * 10) * 1.0,
    ])->all();

    fakeSalesEndpoints(['top_products' => $topTen]);

    $screen = Native::test(Sales::class);

    expect($screen->get('topProducts'))->toHaveCount(5);
});

it('bucketizes long chart series to at most 8 bars', function () {
    $labels = collect(range(1, 30))->map(fn ($i) => "J$i")->all();
    $values = array_fill(0, 30, 10.0);

    fakeSalesEndpoints(['charts' => ['revenue_margin' => ['labels' => $labels, 'revenue' => $values, 'margin' => $values]]]);

    $screen = Native::test(Sales::class);
    $bucketed = $screen->get('caChart');

    expect($bucketed['values'])->toHaveCount(8);
    expect(array_sum($bucketed['values']))->toBe(300.0);
});

it('passes a short chart series through unchanged', function () {
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $bucketed = $screen->get('caChart');

    expect($bucketed['values'])->toBe([100.0, 200.0, 300.0]);
});

it('shows a generic error instead of crashing on failure', function () {
    fakeSalesEndpoints(error: 'boom');

    Native::test(Sales::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'sales-error');
});

it('opens the shop switcher sheet from the shared header trait', function () {
    fakeSalesEndpoints();
    Http::fake(['*/api/v1/shops' => Http::response(['shops' => []])]);

    $screen = Native::test(Sales::class);
    $screen->call('openShopSwitcher');

    expect($screen->get('shopSheetOpen'))->toBeTrue();
});

it('is fully accessible', function () {
    fakeSalesEndpoints();

    Native::test(Sales::class)->assertAccessible();
});
