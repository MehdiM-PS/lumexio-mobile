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
        $this->refresh();
    }

    public function refresh(): void
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

    public function barHeightPercent(array $chart, int $index): int
    {
        $values = $chart['values'] ?? [];
        $max = empty($values) ? 0 : max($values);

        if ($max <= 0) {
            return 0;
        }

        return (int) round((($values[$index] ?? 0) / $max) * 100);
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
