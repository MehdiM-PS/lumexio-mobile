<?php

namespace Lumexio\NativeCharts\Elements;

use Lumexio\NativeCharts\ChartDocument;
use Lumexio\NativeCharts\ChartType;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * `<native:lumexio-chart>` — an interactive chart.
 *
 * The element is authored like any other EDGE element but its wire type is
 * the core `webview` one: the whole chart is a self-contained HTML document
 * (inline CSS + JS, SVG drawing) handed to the platform web view as the
 * `html` prop, so the touch interactions users expect from a chart —
 * crosshair scrubbing, per-point tooltips, legend toggling, pinch-zoom and
 * pan — run at 60fps inside the web view instead of round-tripping every
 * touch through the PHP bridge.
 *
 * Reusing the core webview element (rather than shipping a SwiftUI/Compose
 * renderer of our own) is deliberate: the sandboxed `html` mode already
 * gives us a locked-down surface (opaque origin, no file access, no popups)
 * on both platforms, needs no native build step, and works offline. The
 * cost is that the document travels over the bridge on every render of the
 * hosting screen — which is why {@see ChartDocument} keeps the payload to a
 * few KB and why nothing in the chart calls back into PHP.
 */
class Chart extends Element
{
    /**
     * Wire type — deliberately NOT `lumexio_chart`. The tag name is only
     * how the element is *authored*; what crosses the bridge is a plain
     * core webview node, which both renderers already know how to draw.
     */
    protected string $type = 'webview';

    /** @var array<string, mixed> */
    protected array $config = [];

    public static function make(ChartType $type = ChartType::Line): static
    {
        $element = new static;
        $element->config['type'] = $type->value;

        return $element;
    }

    public function type(ChartType|string $type): static
    {
        $this->config['type'] = $type instanceof ChartType ? $type->value : $type;

        return $this;
    }

    /**
     * X-axis category labels (also the slice names for pie/donut).
     *
     * @param  list<string>  $labels
     */
    public function labels(array $labels): static
    {
        $this->config['labels'] = array_values($labels);

        return $this;
    }

    /**
     * One entry per plotted series.
     *
     * Each entry accepts: `name` (legend label), `data` (list of
     * float|null — nulls break the line rather than spanning the gap),
     * `color` (hex; falls back to the palette), `dashed` (bool),
     * `fill` (bool; defaults to true for area types) and `notes` (a list
     * parallel to `data` whose non-null entries are appended to that
     * point's tooltip row — e.g. a forecast's confidence score).
     *
     * @param  list<array<string, mixed>>  $series
     */
    public function series(array $series): static
    {
        $this->config['series'] = array_values($series);

        return $this;
    }

    /**
     * Single-series convenience: `data([1, 2, 3])` is `series([['data' => [...]]])`.
     *
     * @param  list<float|int|null>  $data
     */
    public function data(array $data, ?string $name = null): static
    {
        return $this->series([array_filter(
            ['name' => $name, 'data' => array_values($data)],
            fn ($value) => $value !== null
        )]);
    }

    /** Suffix appended to every formatted value, e.g. `' €'` or `' u'`. */
    public function unit(string $unit): static
    {
        $this->config['unit'] = $unit;

        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->config['decimals'] = max(0, $decimals);

        return $this;
    }

    /**
     * Index highlighted on load — the crosshair sits there without a tap,
     * so a chart can open on "today" rather than on an empty readout.
     */
    public function focus(?int $index): static
    {
        $this->config['focus'] = $index;

        return $this;
    }

    public function legend(bool $show = true): static
    {
        $this->config['legend'] = $show;

        return $this;
    }

    public function grid(bool $show = true): static
    {
        $this->config['grid'] = $show;

        return $this;
    }

    public function zoom(bool $enabled = true): static
    {
        $this->config['zoom'] = $enabled;

        return $this;
    }

    /**
     * Default series colors, used for any series without its own `color`.
     * Defaults to the theme's primary/accent/success/destructive/secondary.
     *
     * Colors here — as everywhere else in the app — are authored in CSS byte
     * order (`#RRGGBBAA` when they carry alpha); don't pass a `theme()`
     * value through, that one comes back in the native wire order.
     *
     * @param  list<string>  $palette
     */
    public function palette(array $palette): static
    {
        $this->config['palette'] = array_values($palette);

        return $this;
    }

    /** Message drawn in place of the chart when every series is empty. */
    public function emptyText(string $text): static
    {
        $this->config['emptyText'] = $text;

        return $this;
    }

    /**
     * Chrome colors (axis text, gridlines, tooltip). Defaults come from the
     * app's native-ui theme for the current appearance — override only to
     * pin a chart to specific colors, authored in CSS byte order (see
     * {@see palette()}).
     *
     * @param  array<string, string>  $colors
     */
    public function colors(array $colors): static
    {
        $this->config['colors'] = $colors;

        return $this;
    }

    public function applyAttributes(array $attrs): void
    {
        $setters = [
            'type' => fn ($value) => $this->type($value),
            'labels' => fn ($value) => $this->labels((array) $value),
            'series' => fn ($value) => $this->series((array) $value),
            'data' => fn ($value) => $this->data((array) $value),
            'unit' => fn ($value) => $this->unit((string) $value),
            'decimals' => fn ($value) => $this->decimals((int) $value),
            'palette' => fn ($value) => $this->palette((array) $value),
            'colors' => fn ($value) => $this->colors((array) $value),
            'empty-text' => fn ($value) => $this->emptyText((string) $value),
        ];

        foreach ($setters as $attribute => $setter) {
            if (isset($attrs[$attribute])) {
                $setter($attrs[$attribute]);
            }
        }

        // `focus` is set even when null/absent-as-null: `:focus="$selected"`
        // with a null selection means "no highlight", not "leave the default".
        if (array_key_exists('focus', $attrs)) {
            $this->focus($attrs['focus'] === null || $attrs['focus'] === '' ? null : (int) $attrs['focus']);
        }

        foreach (['legend', 'grid', 'zoom'] as $flag) {
            if (isset($attrs[$flag])) {
                $this->{$flag}(filter_var($attrs[$flag], FILTER_VALIDATE_BOOLEAN));
            }
        }

        $this->applyA11yAttributes($attrs);
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return [
            'html' => ChartDocument::make($this->config)->render(),
            // The document is inert without it — everything the chart draws
            // is JS. DOM storage stays off: the chart keeps no state across
            // renders.
            'javascript' => true,
        ];
    }
}
