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

it('does not fight the newly-selected period when the previously-active chip echoes its own deselection', function () {
    // Regression test: each period pill is its own independent <chip>
    // (no shared native:model group exists for chips), so switching
    // periods sends two real dispatch events — the tapped chip's own
    // `selected: true`, and the previously-active chip's `selected:
    // false` echo once the re-render pushes it back down to the client.
    // setSalesPeriod() used to ignore that trailing bool entirely, so
    // the `false` echo blindly reassigned $salesPeriod back to the
    // deselected chip's own key, fighting the just-made selection.
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    expect($screen->get('salesPeriod'))->toBe('month');

    $screen->toggle('sales-period-today', true);
    expect($screen->get('salesPeriod'))->toBe('today');

    $screen->toggle('sales-period-month', false);
    expect($screen->get('salesPeriod'))->toBe('today');
});

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

it('renders the date range in the tree, grouped with the Comparer toggle in the same row', function () {
    // Render-level counterpart to the salesDateRangeLabel() unit assertions
    // above: this proves the `sales-date-range` ref actually renders with
    // the expected text on screen AND that it's grouped with
    // `sales-compare-toggle` in the restructured row (mock lines 231-239),
    // not just that the underlying PHP method returns the right string.
    Carbon::setTestNow(Carbon::parse('2026-08-17'));
    fakeSalesEndpoints();

    $screen = Native::test(Sales::class);
    $screen->call('setSalesPeriod', 'month');
    $tree = $screen->tree();

    $dateRange = findNodeByRef($tree, 'sales-date-range');
    expect($dateRange)->not->toBeNull();
    expect($dateRange['props']['text'] ?? null)->toBe('01 – 17 août 2026');

    $row = findNodeByRef($tree, 'sales-date-range-row');
    expect($row)->not->toBeNull();
    expect(findNodeByRef($row, 'sales-date-range'))->not->toBeNull();
    expect(findNodeByRef($row, 'sales-compare-toggle'))->not->toBeNull();
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

// Each chart is one `webview` wire node carrying its whole document
// (Lumexio\NativeCharts) — the engine inside draws the bars, the axis and the
// tap readout, so these assert the data and labels PHP hands it rather than a
// per-bar node.
it('hands the CA and basket charts their x-axis labels', function () {
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();

    expect(chartConfig(findNodeByRef($tree, 'sales-ca-chart'))['labels'])->toBe(['S1', 'S2', 'S3']);
    expect(chartConfig(findNodeByRef($tree, 'sales-basket-chart'))['labels'])->toBe(['S1', 'S2', 'S3']);
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

it('hands the CA chart its revenue series', function () {
    // Default fixture: revenue_margin values are [100.0, 200.0, 300.0] (3
    // items, no bucketizing).
    fakeSalesEndpoints();

    $config = chartConfig(findNodeByRef(Native::test(Sales::class)->tree(), 'sales-ca-chart'));

    expect($config['type'])->toBe('bar');
    expect($config['unit'])->toBe(' €');
    expect($config['series'][0]['name'])->toBe('CA');
    expect($config['series'][0]['data'])->toBe([100, 200, 300]);
});

it('hands the basket chart its own series and color', function () {
    // Mirrors the CA chart test above, for the Panier moyen chart. This is
    // the only test covering the avgCart field, which is camelCase unlike
    // its sibling `revenue` — a typo there would leave every other test
    // green. The explicit color keeps the two stacked charts from both
    // rendering in the palette's first (brand teal) slot.
    fakeSalesEndpoints(['charts' => ['avg_cart' => ['labels' => ['S1', 'S2', 'S3'], 'avgCart' => [50.0, 60.0, 70.0], 'groupBy' => 'daily']]]);

    $config = chartConfig(findNodeByRef(Native::test(Sales::class)->tree(), 'sales-basket-chart'));

    expect($config['series'][0]['data'])->toBe([50, 60, 70]);
    expect($config['series'][0]['color'])->toBe('#C9A97A');
    expect($config['series'][0]['color'])->not->toBe($config['palette'][0]);
});

it('renders the category breakdown as a donut of per-category amounts', function () {
    // Default fixture: category_breakdown[0] is Chaussures at 34 200 €. The
    // donut is fed amounts, not the server's rounded `pct` — it derives the
    // share itself, so the slices always sum to the whole.
    fakeSalesEndpoints();

    $tree = Native::test(Sales::class)->tree();
    $config = chartConfig(findNodeByRef($tree, 'sales-category-chart'));

    expect($config['type'])->toBe('donut');
    expect($config['labels'])->toBe(['Chaussures', 'Vêtements']);
    expect($config['series'][0]['data'])->toBe([34200, 27900]);

    // The exact amounts stay readable without tapping a slice.
    expect(findNodeByRef($tree, 'sales-category-row-0'))->not->toBeNull();
});

it('hides the category donut but keeps the empty state when there is no breakdown', function () {
    fakeSalesEndpoints(['charts' => ['category_breakdown' => []]]);

    // array_replace_recursive (see fakeSalesEndpoints()) leaves a list
    // untouched when the override is [], so clear it on the instance to
    // exercise the empty branch.
    $screen = Native::test(Sales::class);
    $screen->set('categoryBreakdown', []);

    expect(findNodeByRef($screen->tree(), 'sales-category-chart'))->toBeNull();
    $screen->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'sales-category-empty');
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
