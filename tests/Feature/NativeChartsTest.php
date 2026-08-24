<?php

use Lumexio\NativeCharts\ChartDocument;
use Lumexio\NativeCharts\ChartType;
use Lumexio\NativeCharts\Elements\Chart;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\ElementRegistry;

/**
 * Decode the `window.__LUMEXIO_CHART__` literal out of a rendered document.
 * Same contract as the shared chartConfig() helper, but reading a raw
 * document string rather than a wire node.
 *
 * @return array<string, mixed>
 */
function configFromDocument(string $html): array
{
    expect(preg_match('/^window\.__LUMEXIO_CHART__=(.*);$/m', $html, $matches))->toBe(1);

    return json_decode($matches[1], true);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function chartNode(array $attributes): array
{
    $element = Chart::make();
    $element->applyAttributes($attributes);

    return $element->toArray(new CallbackRegistry);
}

it('registers itself as the lumexio_chart element type', function () {
    // `<native:lumexio-chart …>` is compiled straight into a
    // `lumexio_chart` collector call by NativePHP's tag precompiler — the
    // collector then resolves the type through this registry, so a missing
    // registration is an "Unknown native element type" crash at render.
    expect(ElementRegistry::has('lumexio_chart'))->toBeTrue();
    expect(ElementRegistry::resolve('lumexio_chart'))->toBeInstanceOf(Chart::class);
});

it('crosses the bridge as a core webview node, not a type of its own', function () {
    // The element ships no native renderer: what the platforms receive is a
    // plain `webview` node whose `html` prop is the entire chart document.
    // Emitting `lumexio_chart` on the wire instead would need Swift/Kotlin
    // on the other side to draw anything at all.
    $node = chartNode(['labels' => ['A'], 'data' => [1]]);

    expect($node['type'])->toBe('webview');
    expect($node['props']['javascript'])->toBeTrue();
    expect($node['props']['html'])->toStartWith('<!DOCTYPE html>');
});

it('maps every authoring attribute onto the document config', function () {
    $node = chartNode([
        'type' => 'stacked-bar',
        'labels' => ['Jan', 'Fév'],
        'series' => [['name' => 'CA', 'data' => [1, 2]]],
        'unit' => ' €',
        'decimals' => '2',
        'focus' => '1',
        'legend' => 'false',
        'grid' => 'false',
        'zoom' => 'true',
        'palette' => ['#111111'],
        'colors' => ['text' => '#222222'],
        'empty-text' => 'Rien à afficher',
    ]);

    $config = configFromDocument($node['props']['html']);

    expect($config['type'])->toBe('stacked-bar');
    expect($config['labels'])->toBe(['Jan', 'Fév']);
    expect($config['unit'])->toBe(' €');
    expect($config['decimals'])->toBe(2);
    expect($config['focus'])->toBe(1);
    expect($config['legend'])->toBeFalse();
    expect($config['grid'])->toBeFalse();
    expect($config['zoom'])->toBeTrue();
    expect($config['palette'])->toBe(['#111111']);
    expect($config['colors']['text'])->toBe('#222222');
    expect($config['emptyText'])->toBe('Rien à afficher');
});

it('supports every chart type the enum declares', function (ChartType $type) {
    $config = configFromDocument(ChartDocument::make([
        'type' => $type->value,
        'labels' => ['A', 'B'],
        'series' => [['data' => [1, 2]]],
    ])->render());

    expect($config['type'])->toBe($type->value);

    // Only the continuous types get pinch-zoom by default: zooming a
    // handful of categorical bars or pie slices buys nothing.
    expect($config['zoom'])->toBe($type->supportsZoom());
})->with(ChartType::cases());

it('falls back to a line chart for an unknown type', function () {
    // A typo in a `type="…"` attribute should degrade to the default shape,
    // not render an empty web view with no diagnostic.
    expect(configFromDocument(ChartDocument::make(['type' => 'sunburst', 'series' => [['data' => [1]]]])->render())['type'])
        ->toBe(ChartType::Line->value);
});

it('omits focus entirely when nothing is highlighted', function () {
    // The engine reads a missing `focus` as "no crosshair"; an explicit
    // null would round-trip through JSON and be read as index 0.
    $config = configFromDocument(ChartDocument::make(['series' => [['data' => [1, 2]]], 'focus' => null])->render());

    expect($config)->not->toHaveKey('focus');
});

it('normalizes series values, keeping nulls as gaps', function () {
    // Nulls are load-bearing: they break a line rather than spanning the
    // gap, which is how the forecast chart joins its two curves.
    $config = configFromDocument(ChartDocument::make(['series' => [
        ['data' => [1, null, '', '3.5'], 'notes' => [null, '', 'confiance 82 %', null]],
    ]])->render());

    expect($config['series'][0]['data'])->toBe([1, null, null, 3.5]);
    expect($config['series'][0]['notes'])->toBe([null, null, 'confiance 82 %', null]);
});

it('takes its default palette and chrome colors from the app theme', function () {
    // The document is a sandboxed page with no access to the app's CSS, so
    // the only way it matches the current appearance is by being handed the
    // resolved tokens (config/native-ui.php).
    $config = configFromDocument(ChartDocument::make(['series' => [['data' => [1]]]])->render());

    expect($config['palette'][0])->toBe('#2D5D5A');
    expect($config['palette'][1])->toBe('#EC7C0E');
    // The `outline` token carries alpha, and theme() returns it in the
    // native wire order (#AARRGGBB). A browser reads that as #RRGGBBAA — a
    // near-transparent red instead of a faint grey gridline — so the
    // document has to flip it back to CSS order.
    expect(theme('outline'))->toBe('#14111111');
    expect($config['colors']['grid'])->toBe('#11111114');
});

it('leaves a caller-supplied alpha color in the CSS order it was authored in', function () {
    // Only theme() values arrive in the native wire order. Every color prop
    // in this project is authored CSS-first, so rotating a caller's bytes
    // too would corrupt them.
    $config = configFromDocument(ChartDocument::make([
        'series' => [['data' => [1], 'color' => '#8B5CF680']],
    ])->render());

    expect($config['series'][0]['color'])->toBe('#8B5CF680');
});

it('describes the chart for screen readers that reach inside the web view', function () {
    $config = configFromDocument(ChartDocument::make([
        'type' => 'bar',
        'labels' => ['01/08', '03/08'],
        'series' => [['data' => [100.0, 250.0]]],
        'unit' => ' €',
    ])->render());

    expect($config['summary'])->toBe('Graphique bar, 2 points, de 01/08 à 03/08, de 100 € à 250 €');
});

it('describes an empty chart without dividing by an empty series', function () {
    // min()/max() on an empty array is a PHP ValueError — the summary has
    // to short-circuit before it gets there.
    expect(configFromDocument(ChartDocument::make(['series' => []])->render())['summary'])
        ->toBe('Graphique sans données');
});

it('cannot be broken out of by a label containing markup', function () {
    // Labels are merchant data (category and product names). A raw `<` in
    // the JSON literal would end the <script> element early and leave the
    // rest of the engine as page text.
    $html = ChartDocument::make([
        'labels' => ['</script><img src=x onerror=alert(1)>'],
        'series' => [['data' => [1]]],
    ])->render();

    expect($html)->not->toContain('</script><img');
    expect(substr_count($html, '</script>'))->toBe(1);
    expect(configFromDocument($html)['labels'][0])->toBe('</script><img src=x onerror=alert(1)>');
});

it('inlines a compacted stylesheet and engine', function () {
    // The document is a prop on a wire node — it crosses the bridge again
    // whenever a chart's data changes — so ChartDocument::compact() strips
    // the comments and indentation that make the assets maintainable. This
    // asserts both that the assets are actually there and that compaction
    // ran (no indented lines, no whole-line comments survive).
    $html = ChartDocument::make(['series' => [['data' => [1]]]])->render();

    expect($html)->toContain('<style>')->toContain('(function () {');
    expect($html)->not->toContain("\n    ");
    expect($html)->not->toContain("\n//");
    expect($html)->not->toContain('/**');
});
