<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class ForecastSection extends NativeComponent
{
    use HandlesApiErrors;

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
     * next 7 forecast days (per spec Decision 4 — the API returns a wider
     * 30-day historical window, sliced client-side to keep the chart
     * readable on a phone width).
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
        return view('native.forecast-section');
    }
}
