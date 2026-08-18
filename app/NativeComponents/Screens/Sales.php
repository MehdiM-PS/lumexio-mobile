<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Sales extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    public string $salesPeriod = 'month';

    public bool $compareEnabled = true;

    public array $metrics = [
        'current' => ['revenue' => 0, 'orders' => 0, 'avg_order' => 0, 'new_customers' => 0],
        'previous' => ['revenue' => 0, 'orders' => 0, 'avg_order' => 0, 'new_customers' => 0],
        'changes' => ['revenue' => 0, 'orders' => 0, 'avg_order' => 0, 'new_customers' => 0],
    ];

    public array $topProducts = [];

    public array $categoryBreakdown = [];

    public array $caChart = ['labels' => [], 'values' => []];

    public array $basketChart = ['labels' => [], 'values' => []];

    private const PERIOD_LABELS = [
        'today' => "Aujourd'hui",
        'yesterday' => 'Hier',
        'week' => 'Cette sem.',
        'last_week' => 'Sem. préc.',
        'month' => 'Ce mois',
        'last_month' => 'Mois préc.',
        'year' => 'Cette année',
        'last_year' => 'An. préc.',
    ];

    public function mount(): void
    {
        $this->loadCurrentUser();
        $this->load();
    }

    /** Bound to pull-to-refresh; always bypasses the short-lived GET cache. */
    public function refresh(): void
    {
        $this->bustApiCache();
        $this->load();
    }

    private function load(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/revenue', ['period' => $this->salesPeriod]));

        $this->metrics = $data['metrics'] ?? $this->metrics;
        $this->topProducts = array_slice($data['top_products'] ?? [], 0, 5);
        $this->categoryBreakdown = $data['charts']['category_breakdown'] ?? [];

        $revenueChart = $data['charts']['revenue_margin'] ?? ['labels' => [], 'revenue' => []];
        $this->caChart = $this->bucketize($revenueChart['labels'] ?? [], $revenueChart['revenue'] ?? []);

        $avgCartChart = $data['charts']['avg_cart'] ?? ['labels' => [], 'avgCart' => []];
        $this->basketChart = $this->bucketize($avgCartChart['labels'] ?? [], $avgCartChart['avgCart'] ?? []);
    }

    public function setSalesPeriod(string $key): void
    {
        $this->salesPeriod = $key;
        $this->refresh();
    }

    public function periodLabel(): string
    {
        return self::PERIOD_LABELS[$this->salesPeriod] ?? '';
    }

    /**
     * Exposes PERIOD_LABELS to the view so the period-picker's chip loop
     * doesn't maintain a second, independently-edited copy of the same map.
     *
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return self::PERIOD_LABELS;
    }

    /**
     * Absolute pixel bar height, not a percentage: EDGE's `height` attribute
     * (unlike `width`, which also accepts a Tailwind-fraction-style percentage
     * string) is read raw by NativeElementCollector::buildLayoutArray() with no
     * percentage support, and `style="height:...%"` is never read at all (no
     * renderer path parses a raw `style` attribute) — so a percentage here
     * would render as either a nonsensical literal or nothing. $containerHeight
     * is the caller's known fixed pixel height for the chart row it's scaling
     * against (matches ItemDetail::barHeight()'s same absolute-pixel approach
     * for the stock-history chart).
     *
     * Floors at 4px (matching ItemDetail::barHeight()'s own floor) rather than
     * returning 0: both native height modifiers (iOS's NodeLayoutModifier,
     * Android's NodeView) guard on `height > 0` — a literal 0 doesn't collapse
     * the bar, it drops the height constraint entirely, letting the bar grow
     * unbounded. A 4px floor renders a deliberate, barely-visible sliver
     * instead.
     */
    public function barHeightPx(array $chart, int $index, int $containerHeight): int
    {
        $values = $chart['values'] ?? [];
        $max = empty($values) ? 0 : max($values);

        if ($max <= 0) {
            return 4;
        }

        return max(4, (int) round((($values[$index] ?? 0) / $max) * $containerHeight));
    }

    private function bucketize(array $labels, array $values, int $maxBars = 8): array
    {
        // Cast to float: values arrive from LumexioApi::get() having gone
        // through a JSON round-trip, which collapses whole-number floats
        // (e.g. 10.0) to ints. Chart series are always monetary/decimal
        // values, so normalize to float here rather than leaving the type
        // to whatever the wire happened to preserve.
        $values = array_map(fn ($value) => (float) $value, $values);
        $count = count($values);

        if ($count === 0) {
            return ['labels' => [], 'values' => []];
        }

        if ($count <= $maxBars) {
            return ['labels' => $labels, 'values' => $values];
        }

        $chunkSize = (int) ceil($count / $maxBars);
        $bucketLabels = [];
        $bucketValues = [];

        foreach (array_chunk($values, $chunkSize) as $i => $chunk) {
            $bucketValues[] = array_sum($chunk);
            $labelChunk = array_slice($labels, $i * $chunkSize, count($chunk));
            $bucketLabels[] = $labelChunk[0] ?? '';
        }

        return ['labels' => $bucketLabels, 'values' => $bucketValues];
    }

    public function render(): View
    {
        return view('native.sales');
    }
}
