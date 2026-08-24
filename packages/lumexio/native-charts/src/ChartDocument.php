<?php

namespace Lumexio\NativeCharts;

/**
 * Assembles the self-contained HTML document a chart web view renders.
 *
 * Everything the chart needs — stylesheet, engine, data — is inlined: the
 * document loads with `null` as its base URL (an opaque origin), so it can
 * neither reach the network nor read app files, and it keeps working with
 * the device offline.
 */
final class ChartDocument
{
    /** Fallback chrome colors, used when the app has no native-ui theme. */
    private const array FALLBACK_COLORS = [
        'text' => '#14151A',
        'muted' => '#6D6F78',
        'grid' => '#11111114',
        'tooltipBg' => '#14151A',
        'tooltipText' => '#FFFFFF',
    ];

    private const array FALLBACK_PALETTE = ['#2D5D5A', '#EC7C0E', '#36A558', '#E24947', '#6D6F78'];

    /** Compacted asset bodies, built once per process. */
    private static ?string $script = null;

    private static ?string $style = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): self
    {
        return new self($config);
    }

    public function render(): string
    {
        $config = $this->resolvedConfig();

        // JSON_HEX_* keeps the payload free of characters that could close
        // the <script> element or break out of the document, so no chart
        // label — however it was typed by a merchant — can reshape the page.
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no,viewport-fit=cover">'
            .'<style>'.self::style().'</style></head>'
            .'<body><div class="wrap"><div id="chart"></div><div id="tip" hidden></div><div id="legend" hidden></div></div>'
            // The config literal gets a line to itself: json_encode never
            // emits a raw newline, so "everything up to the end of that
            // line" is an unambiguous way to read the configuration back
            // out of a rendered document (see the chartConfig() test
            // helper).
            .'<script>'."\n".'window.__LUMEXIO_CHART__='.$json.';'."\n".self::script()."\n".'</script>'
            .'</body></html>';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedConfig(): array
    {
        $type = ChartType::tryFrom($this->config['type'] ?? '') ?? ChartType::Line;
        $series = $this->normalizedSeries();
        $labels = array_map(fn ($label) => (string) $label, $this->config['labels'] ?? []);

        $config = [
            'type' => $type->value,
            'labels' => $labels,
            'series' => $series,
            'unit' => (string) ($this->config['unit'] ?? ''),
            'decimals' => (int) ($this->config['decimals'] ?? 0),
            'palette' => $this->config['palette'] ?? self::defaultPalette(),
            'colors' => array_merge(self::defaultColors(), $this->config['colors'] ?? []),
            'grid' => (bool) ($this->config['grid'] ?? true),
            'zoom' => (bool) ($this->config['zoom'] ?? $type->supportsZoom()),
            'emptyText' => (string) ($this->config['emptyText'] ?? 'Aucune donnée'),
            'summary' => $this->summary($type, $series, $labels),
        ];

        // `legend` and `focus` are only sent when explicitly set: the engine
        // treats an absent legend flag as "show it when there is more than
        // one entry", and an absent focus as "nothing highlighted".
        if (array_key_exists('legend', $this->config)) {
            $config['legend'] = (bool) $this->config['legend'];
        }

        if (($this->config['focus'] ?? null) !== null) {
            $config['focus'] = (int) $this->config['focus'];
        }

        return $config;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedSeries(): array
    {
        return array_values(array_map(function (array $series): array {
            $normalized = [
                'data' => array_values(array_map(
                    fn ($value) => $value === null || $value === '' ? null : (float) $value,
                    $series['data'] ?? []
                )),
            ];

            foreach (['name', 'color'] as $key) {
                if (isset($series[$key]) && $series[$key] !== '') {
                    $normalized[$key] = (string) $series[$key];
                }
            }

            foreach (['dashed', 'fill'] as $key) {
                if (isset($series[$key])) {
                    $normalized[$key] = (bool) $series[$key];
                }
            }

            if (! empty($series['notes'])) {
                $normalized['notes'] = array_values(array_map(
                    fn ($note) => $note === null || $note === '' ? null : (string) $note,
                    $series['notes']
                ));
            }

            return $normalized;
        }, $this->config['series'] ?? []));
    }

    /**
     * One-line description read by the web view's own screen-reader tree.
     * The native element's `a11y-label` announces the chart as a whole; this
     * covers the case where focus lands inside the web view instead.
     *
     * @param  list<array<string, mixed>>  $series
     * @param  list<string>  $labels
     */
    private function summary(ChartType $type, array $series, array $labels): string
    {
        $values = [];
        foreach ($series as $entry) {
            foreach ($entry['data'] as $value) {
                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }

        if ($values === []) {
            return 'Graphique sans données';
        }

        $range = $labels === []
            ? ''
            : ', de '.reset($labels).' à '.end($labels);

        return sprintf(
            'Graphique %s, %d points%s, de %s à %s',
            $type->value,
            count($values),
            $range,
            $this->formatForSummary(min($values)),
            $this->formatForSummary(max($values)),
        );
    }

    private function formatForSummary(float $value): string
    {
        return number_format($value, (int) ($this->config['decimals'] ?? 0), ',', ' ')
            .($this->config['unit'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    private static function defaultColors(): array
    {
        if (! function_exists('theme')) {
            return self::FALLBACK_COLORS;
        }

        return array_map(self::cssColor(...), [
            'text' => (string) theme('on-background', self::FALLBACK_COLORS['text']),
            'muted' => (string) theme('on-surface-variant', self::FALLBACK_COLORS['muted']),
            'grid' => (string) theme('outline', self::FALLBACK_COLORS['grid']),
            'tooltipBg' => (string) theme('on-surface', self::FALLBACK_COLORS['tooltipBg']),
            'tooltipText' => (string) theme('surface', self::FALLBACK_COLORS['tooltipText']),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function defaultPalette(): array
    {
        if (! function_exists('theme')) {
            return self::FALLBACK_PALETTE;
        }

        return array_map(self::cssColor(...), [
            (string) theme('primary', self::FALLBACK_PALETTE[0]),
            (string) theme('accent', self::FALLBACK_PALETTE[1]),
            (string) theme('success', self::FALLBACK_PALETTE[2]),
            (string) theme('destructive', self::FALLBACK_PALETTE[3]),
            (string) theme('secondary', self::FALLBACK_PALETTE[4]),
        ]);
    }

    /**
     * Rewrite an alpha-bearing hex color from the theme into CSS byte order.
     *
     * `theme()` hands back colors in the *native wire* order NativePHP's
     * renderers expect — `#AARRGGBB` — so the app's `outline` token, authored
     * as `#11111114`, arrives here as `#14111111`. A browser reads that as
     * `#RRGGBBAA` and paints a near-transparent red where a faint grey
     * gridline belongs, so the two leading bytes move to the back. Colors
     * without alpha (`#RGB`, `#RRGGBB`, and any named/functional form) pass
     * through untouched.
     *
     * Applied ONLY to values read back out of `theme()`. Colors handed to
     * the element by a caller are authored in CSS order already (that is the
     * project-wide convention for every color prop), and rotating those too
     * would corrupt them — and rotating twice corrupts either way.
     */
    private static function cssColor(string $color): string
    {
        return preg_match('/^#([0-9a-f]{2})([0-9a-f]{6})$/i', $color, $matches) === 1
            ? '#'.$matches[2].$matches[1]
            : $color;
    }

    private static function script(): string
    {
        return self::$script ??= self::compact(__DIR__.'/../resources/dist/lumexio-charts.js');
    }

    private static function style(): string
    {
        return self::$style ??= self::compact(__DIR__.'/../resources/dist/lumexio-charts.css');
    }

    /**
     * Strip the asset down to what the device needs to run it: whole-line
     * comments, leading indentation and blank lines go, everything else is
     * left byte-for-byte alone.
     *
     * This is not a minifier and must never grow into one — it only removes
     * text that can be identified from the start of a line, which is safe
     * for these two hand-written files (neither has a string literal or a
     * regex starting a line with `//` or `/*`). It exists because the
     * document is a prop on a wire node: it crosses the PHP-to-native
     * bridge again every time a chart's data changes, and the comments
     * that make the engine maintainable are a third of its bytes.
     */
    private static function compact(string $path): string
    {
        $lines = explode("\n", (string) file_get_contents($path));
        $out = [];
        $inBlockComment = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($inBlockComment) {
                $inBlockComment = ! str_contains($trimmed, '*/');

                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if (str_starts_with($trimmed, '/*')) {
                $inBlockComment = ! str_contains($trimmed, '*/');

                continue;
            }

            $out[] = $trimmed;
        }

        // Newline-joined, not space-joined: JavaScript's automatic semicolon
        // insertion and `//`-style trailing comments both depend on line
        // breaks surviving.
        return implode("\n", $out);
    }
}
