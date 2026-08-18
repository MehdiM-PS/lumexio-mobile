<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use App\Services\LumexioApi;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    /**
     * `$selected` is the chip's emitted on_change value, appended by the
     * dispatcher after the callback's own args. Each period is its own
     * independent `<chip>` (no shared native:model group exists for
     * chips), so switching periods fires this callback twice: once with
     * `selected: true` from the tapped chip, and once with `selected:
     * false` from the previously-active chip's own echo when the
     * re-render pushes its new, deselected value back down to it.
     * Without this guard, that `false` echo would blindly reassign
     * $salesPeriod back to that chip's own key, fighting the just-made
     * selection in an oscillating loop.
     */
    public function setSalesPeriod(string $key, bool $selected = true): void
    {
        if (! $selected) {
            return;
        }

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
     * The actual calendar date range for the selected period, e.g.
     * "01 – 17 août 2026" — mirrors the mock's `salesDateRange` (line 232),
     * shown next to the Comparer toggle, distinct from periodLabel()'s
     * friendly "Ce mois" shown under the title.
     *
     * Live-computed off today() rather than the mock's own hardcoded
     * example strings: those are static illustrative literals for a demo,
     * not values a real, date-driven screen should freeze forever. French
     * month names come from Carbon's bundled 'fr' translations (no app
     * locale/lang-file setup needed) — set only on the instances used here,
     * not globally via Carbon::setLocale(), so this has no side effect on
     * any other screen's date formatting.
     *
     * Deliberately does not replicate the mock's own inconsistent month
     * abbreviation in its example strings (full "août" for the current/
     * to-date periods, abbreviated "juil." for the fully-past "Mois préc."
     * example) — those are two different hardcoded literals, not a
     * documented rule, so full month names are used everywhere here.
     */
    public function salesDateRangeLabel(): string
    {
        $today = Carbon::today();

        if ($this->salesPeriod === 'last_year') {
            return $today->copy()->subYear()->format('Y');
        }

        [$start, $end] = match ($this->salesPeriod) {
            'today' => [$today, $today],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->startOfWeek(), $today],
            'last_week' => [
                $today->copy()->subWeek()->startOfWeek(),
                $today->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'year' => [$today->copy()->startOfYear(), $today],
            default => [$today, $today],
        };

        return $this->formatDateRange($start, $end);
    }

    private function formatDateRange(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->locale('fr')->translatedFormat('d F Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('d').' – '.$end->locale('fr')->translatedFormat('d F Y');
        }

        if ($start->isSameYear($end)) {
            return $start->locale('fr')->translatedFormat('d F').' – '.$end->locale('fr')->translatedFormat('d F Y');
        }

        return $start->locale('fr')->translatedFormat('d F Y').' – '.$end->locale('fr')->translatedFormat('d F Y');
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
