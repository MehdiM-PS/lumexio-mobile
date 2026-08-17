<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Forecasts extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    public array $historical = [];

    public array $forecasts = [];

    public array $summary = [];

    public ?int $selectedProductId = null;

    public ?string $selectedProductName = null;

    public ?int $selectedBarIndex = null;

    public string $productSearch = '';

    public array $productResults = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();
        // Loaded first, matching Dashboard/Alerts/Orders/Stock/Recommendations
        // — so that when both this and the screen's own fetch fail, the
        // screen's own error is the one left in $lastApiError.
        $this->loadCurrentUser();
        $this->selectedBarIndex = null;

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/forecasts', array_filter([
            'product_id' => $this->selectedProductId,
        ])));

        $this->historical = $data['historical'] ?? [];
        $this->forecasts = $data['forecasts'] ?? [];
        $this->summary = $data['summary'] ?? [];
    }

    public function updatedProductSearch(): void
    {
        if (mb_strlen($this->productSearch) < 2) {
            $this->productResults = [];

            return;
        }

        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/products', [
            'search' => $this->productSearch,
            'per_page' => 10,
        ]));

        $this->productResults = $data['products'] ?? [];
    }

    public function selectProduct(int $productId): void
    {
        $product = collect($this->productResults)->firstWhere('id', $productId);

        $this->selectedProductId = $productId;
        $this->selectedProductName = $product['name'] ?? null;
        $this->productSearch = '';
        $this->productResults = [];
        $this->refresh();
    }

    public function clearProduct(): void
    {
        $this->selectedProductId = null;
        $this->selectedProductName = null;
        $this->refresh();
    }

    /**
     * Combined, date-sorted series: the last 14 historical days plus the
     * next 7 forecast days (kept from the retired ForecastSection — the
     * API returns a wider 30-day historical window, sliced client-side
     * to keep the chart readable on a phone width).
     *
     * @return list<array{date: string, revenue: float, type: 'historical'|'forecast', confidence: int|null}>
     */
    public function chartSeries(): array
    {
        $historical = collect($this->historical)
            ->sortBy('date')
            ->slice(-14)
            ->map(fn (array $row) => [
                'date' => $row['date'],
                'revenue' => (float) $row['revenue'],
                'type' => 'historical',
                'confidence' => null,
            ]);

        $forecast = collect($this->forecasts)
            ->sortBy('forecast_date')
            ->take(7)
            ->map(fn (array $row) => [
                'date' => $row['forecast_date'],
                'revenue' => (float) $row['predicted_revenue'],
                'type' => 'forecast',
                'confidence' => (int) $row['confidence_score'],
            ]);

        return $historical->concat($forecast)->values()->all();
    }

    /**
     * Confidence percentage shown in the header readout: the shop-level
     * summary's confidence when no product is selected, otherwise the mean
     * confidence across the *charted* forecast days (chartSeries()'s
     * take(7) window, so the header stays consistent with what the bars
     * actually show) for the selected product. This keeps a confidence
     * indicator visible by default as soon as a product is selected, rather
     * than only after tapping a specific forecast bar. Returns null (hide
     * the readout) only when a product is selected but has no forecast data
     * yet, to avoid showing a misleading "Confiance : 0%".
     */
    public function headerConfidence(): ?int
    {
        if ($this->selectedProductId === null) {
            return (int) ($this->summary['confidence'] ?? 0);
        }

        $scores = collect($this->chartSeries())
            ->where('type', 'forecast')
            ->pluck('confidence')
            ->filter(fn ($score) => $score !== null);

        return $scores->isEmpty() ? null : (int) round($scores->avg());
    }

    public function barHeight(float $value): int
    {
        $values = array_column($this->chartSeries(), 'revenue');
        $max = max($values ?: [1]);

        return $max > 0 ? max(4, (int) round(($value / $max) * 72)) : 4;
    }

    public function selectBar(int $index): void
    {
        $this->selectedBarIndex = $this->selectedBarIndex === $index ? null : $index;
    }

    public function render(): View
    {
        return view('native.forecasts');
    }
}
