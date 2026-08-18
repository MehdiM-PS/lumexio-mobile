<?php

use App\NativeComponents\Screens\Sales;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

afterEach(function () {
    Carbon::setTestNow();
});

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

    // Mock (line 246, e.g. salesMetrics[0].delta): "+12,0% vs période -1" —
    // one decimal and the "vs période -1" suffix, not a bare "+12%".
    Native::visit('/sales')
        ->assertSee('89 400,00 €')
        ->assertSee('612')
        ->assertSee('146,00 €')
        ->assertSee('89')
        ->assertSee('+12,0% vs période -1');
});

it('shows the comparison table by default (compareEnabled starts true)', function () {
    fakeSalesEndpoints();

    Native::visit('/sales')
        ->assertSee('Actuelle vs période -1')
        ->assertSee('79 800,00 €');
});

it('nests a colored delta under the previous-period value in the comparison table', function () {
    // Mock (line 261): the "Période -1" cell renders the raw previous value
    // AND a bold, colored delta line beneath it — not the previous value
    // alone.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();

    $delta = findNodeByRef($tree, 'sales-compare-0-delta');
    expect($delta)->not->toBeNull();
    expect($delta['props']['text'] ?? null)->toBe('+12,0%');
    expect($delta['props']['color'] ?? null)->toBe(theme('success'));
});

it('shows the actual calendar date range for the selected period next to Comparer', function () {
    // Mock (line 674, salesPeriodDefs.month.range): "01 – 17 août 2026" for
    // the "month" period when today is 2026-08-17.
    Carbon::setTestNow(Carbon::parse('2026-08-17'));
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', 'month');

    expect($screen->instance()->salesDateRangeLabel())->toBe('01 – 17 août 2026');
});

it('shows a bare year for the last_year period', function () {
    // Mock (line 677): salesPeriodDefs.lastyear.range is the bare "2025" —
    // no day/month, since the whole prior year is implied.
    Carbon::setTestNow(Carbon::parse('2026-08-17'));
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', 'last_year');

    expect($screen->instance()->salesDateRangeLabel())->toBe('2025');
});

it('shows a single date for the today period', function () {
    // Mock (line 670): salesPeriodDefs.today.range is "17 août 2026" — the
    // isSameDay branch of formatDateRange(), otherwise untested.
    Carbon::setTestNow(Carbon::parse('2026-08-17'));
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', 'today');

    expect($screen->instance()->salesDateRangeLabel())->toBe('17 août 2026');
});

it('spells out both month names for the year-to-date period when it crosses months', function () {
    // Mock (line 676): salesPeriodDefs.year.range is "01 janv. – 17 août
    // 2026" (start of year to today) — the isSameYear-but-not-isSameMonth
    // branch of formatDateRange(), otherwise untested. This app's version
    // uses the full "janvier" rather than the mock's abbreviated "janv."
    // (see Sales::salesDateRangeLabel()'s docblock for why).
    Carbon::setTestNow(Carbon::parse('2026-08-17'));
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', 'year');

    expect($screen->instance()->salesDateRangeLabel())->toBe('01 janvier – 17 août 2026');
});

it('shows the x-axis labels under the CA and basket charts', function () {
    // Mock (lines 276-280, 292-296): each chart renders a label row under
    // the bars using the same series labels already used for a11y-label.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();

    expect(findNodeByRef($tree, 'sales-ca-bar-0-label')['props']['text'] ?? null)->toBe('S1');
    expect(findNodeByRef($tree, 'sales-basket-bar-0-label')['props']['text'] ?? null)->toBe('S1');
});

it('gives the comparison table columns a flex weight so they align across rows', function () {
    // Guards against a regression to an unsupported arbitrary-value class
    // (e.g. `flex-[1.3]`, which EDGE's TailwindParser does not parse —
    // verified via TailwindParser::parse(), which silently drops it and
    // returns no flexGrow at all). Without a real flexGrow on each column,
    // the three "columns" land at different x-positions on every row
    // instead of aligning as a table.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();
    $label = findNodeByRef($tree, 'sales-compare-header-label');

    expect($label)->not->toBeNull();
    expect($label['layout']['flex_grow'] ?? null)->toBe(1.0);
});

it('hides the comparison table when the compare toggle is off', function () {
    fakeSalesEndpoints();

    Native::test(Sales::class)
        ->toggle('sales-compare-toggle', false)
        ->assertDontSee('Actuelle vs période -1');
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

it('renders the CA chart bars with an absolute pixel height proportional to their value', function () {
    // Default fixture: revenue_margin values are [100.0, 200.0, 300.0] (3
    // items, no bucketizing). Container height is 90px (matches the row's
    // h-[90] class in the blade), so bar 0 (100/300 of max) => round(90/3) =
    // 30px, and the max-value bar (index 2) => the full 90px. Proves the
    // bar renders as a raw `height` attribute (an absolute pixel value in
    // the resolved layout), not the framework-inert `style="height:...%"`
    // the brief originally specified.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();

    $bar0 = findNodeByRef($tree, 'sales-ca-bar-0');
    $bar2 = findNodeByRef($tree, 'sales-ca-bar-2');

    expect($bar0)->not->toBeNull();
    expect($bar2)->not->toBeNull();
    // The `height` attribute is coerced to float somewhere in the Blade
    // attribute pipeline before it reaches the resolved layout (unrelated
    // to barHeightPx()'s own int return type) — assert the float it
    // actually carries rather than the PHP-side int.
    expect($bar0['layout']['height'] ?? null)->toBe(30.0);
    expect($bar2['layout']['height'] ?? null)->toBe(90.0);
});

it('renders the basket chart bars with an absolute pixel height proportional to their value', function () {
    // Mirrors the CA chart bar-height test above, but for the Panier moyen
    // chart: fake charts.avg_cart.avgCart is [50.0, 60.0, 70.0] (3 items, no
    // bucketizing). Container height is 70px (matches the row's h-[70] class
    // in the blade), so bar 0 (50/70 of max) => round(70*50/70) = 50px, and
    // the max-value bar (index 2) => the full 70px. This is the only test
    // covering the avgCart field, which is camelCase unlike its sibling
    // `revenue` — a typo there would leave every other test green.
    fakeSalesEndpoints(['charts' => ['avg_cart' => ['labels' => ['S1', 'S2', 'S3'], 'avgCart' => [50.0, 60.0, 70.0], 'groupBy' => 'daily']]]);

    $tree = Native::test(Sales::class)->tree();

    $bar0 = findNodeByRef($tree, 'sales-basket-bar-0');
    $bar2 = findNodeByRef($tree, 'sales-basket-bar-2');

    expect($bar0)->not->toBeNull();
    expect($bar2)->not->toBeNull();
    expect($bar0['layout']['height'] ?? null)->toBe(50.0);
    expect($bar2['layout']['height'] ?? null)->toBe(70.0);
});

it('renders the category breakdown fill with a percentage width', function () {
    // Default fixture: category_breakdown[0].pct is 38. Proves the fill
    // renders as a raw `width` attribute carrying a percentage string (a
    // renderer-supported route, unlike `style="width:...%"`), not merely
    // baked into a class the wire tree can't be asserted against.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();

    $fill = findNodeByRef($tree, 'sales-category-fill-0');

    expect($fill)->not->toBeNull();
    expect($fill['layout']['width'] ?? null)->toBe('38%');
});

it('floors chart bars at a non-zero pixel height when every value is zero', function () {
    // A shop with zero revenue for the selected period returns an all-zero
    // series. barHeightPx()'s $max <= 0 branch must not return a literal 0:
    // both native height modifiers (iOS NodeLayoutModifier, Android NodeView)
    // guard on `height > 0` and skip applying the constraint entirely when
    // it's 0, so every bar would grow unbounded instead of collapsing —
    // worse than invisible. A floored value (>0) keeps the height
    // constraint applied on both platforms.
    fakeSalesEndpoints(['charts' => ['revenue_margin' => ['labels' => ['S1', 'S2', 'S3'], 'revenue' => [0.0, 0.0, 0.0], 'margin' => [0.0, 0.0, 0.0]]]]);

    $tree = Native::test(Sales::class)->tree();
    $bar0 = findNodeByRef($tree, 'sales-ca-bar-0');

    expect($bar0)->not->toBeNull();
    expect($bar0['layout']['height'] ?? null)->toBeGreaterThan(0);
});

it('floors the category fill width at a non-zero percentage when pct is zero', function () {
    // Same >0 guard applies to the width-percent branch — a literal "0%"
    // would drop the width constraint entirely rather than rendering an
    // empty fill.
    fakeSalesEndpoints(['charts' => ['category_breakdown' => [
        ['label' => 'Accessoires', 'amount' => 0.0, 'pct' => 0],
    ]]]);

    $tree = Native::test(Sales::class)->tree();
    $fill = findNodeByRef($tree, 'sales-category-fill-0');

    expect($fill)->not->toBeNull();
    $width = $fill['layout']['width'] ?? null;

    expect($width)->not->toBe('0%');
    expect((float) rtrim((string) $width, '%'))->toBeGreaterThan(0);
});

it('shows empty states for category breakdown and top products when both are empty', function () {
    // fakeSalesEndpoints()'s override merging uses array_replace_recursive,
    // which does NOT clear a list when the override value is []: recursing
    // into an empty override array leaves the base array's entries
    // untouched (verified — array_replace_recursive($defaults, ['top_products' => []])
    // keeps the default products). So the empty-array case is faked
    // directly here instead of going through the helper's override param.
    Http::fake([
        '*/auth/me' => Http::response(['user' => ['name' => 'Test User', 'email' => 'test@example.com']]),
        '*/revenue*' => Http::response([
            'metrics' => [
                'current' => ['revenue' => 89400.0, 'orders' => 612, 'avg_order' => 146.0, 'new_customers' => 89],
                'previous' => ['revenue' => 79800.0, 'orders' => 567, 'avg_order' => 141.0, 'new_customers' => 77],
                'changes' => ['revenue' => 12.0, 'orders' => 8.0, 'avg_order' => 3.0, 'new_customers' => 15.0],
            ],
            'top_products' => [],
            'charts' => [
                'revenue_margin' => ['labels' => ['S1', 'S2', 'S3'], 'revenue' => [100.0, 200.0, 300.0], 'margin' => [10.0, 20.0, 30.0]],
                'avg_cart' => ['labels' => ['S1', 'S2', 'S3'], 'avgCart' => [50.0, 60.0, 70.0], 'groupBy' => 'daily'],
                'category_breakdown' => [],
            ],
        ], 200),
    ]);

    Native::test(Sales::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'sales-category-empty')
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'sales-products-empty');
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
