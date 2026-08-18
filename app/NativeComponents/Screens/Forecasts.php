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

    public string $stockSearch = '';

    public ?int $stockCategoryId = null;

    public string $stockCategorySearch = '';

    public bool $stockCatOpen = false;

    public bool $stockHideInactive = true;

    public bool $stockHideOOS = false;

    public string $stockSort = 'name';

    public string $stockDir = 'asc';

    public int $stockPage = 1;

    public array $stockItems = [];

    public array $stockCategories = [];

    public int $stockLastPage = 1;

    /** sort values the /products endpoint accepts server-side (Task 1) — avg_sales is client-side-only, see setStockSort(). */
    private const array SERVER_SORTABLE_COLUMNS = ['name', 'stock', 'days_left'];

    public function mount(): void
    {
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

        $this->loadStockCategories();
        $this->loadStock();
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

    private function loadStockCategories(): void
    {
        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/products/categories'));

        $this->stockCategories = $data['categories'] ?? [];
    }

    /**
     * `stockCategorySearch` filters this already-loaded list client-side —
     * /products/categories (Task 1) has no search param of its own, and the
     * category count per shop is small enough that a client-side filter over
     * the full list is simpler than adding server-side search support.
     *
     * @return list<array{id: int, name: string}>
     */
    public function filteredStockCategories(): array
    {
        $search = mb_strtolower(trim($this->stockCategorySearch));

        if ($search === '') {
            return $this->stockCategories;
        }

        return collect($this->stockCategories)
            ->filter(fn (array $category) => str_contains(mb_strtolower($category['name'] ?? ''), $search))
            ->values()
            ->all();
    }

    /**
     * Callers outside load() (search, category select, both toggles, sort,
     * pagination) are responsible for calling resetApiError() first, same
     * contract as updatedProductSearch() — load() already resets once for
     * its whole sequential chain (forecasts, then categories, then this),
     * so resetting again in here would wipe a genuine forecasts/categories
     * failure the moment this call happens to succeed.
     */
    private function loadStock(): void
    {
        // avg_sales is client-side-only (see setStockSort()) — the /products
        // endpoint 422s on any sort value outside SERVER_SORTABLE_COLUMNS, so
        // never send it.
        $sort = in_array($this->stockSort, self::SERVER_SORTABLE_COLUMNS, true) ? $this->stockSort : null;

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/products', array_filter([
            'search' => $this->stockSearch ?: null,
            'category_ids' => $this->stockCategoryId !== null ? [$this->stockCategoryId] : null,
            'include_inactive' => ! $this->stockHideInactive,
            'exclude_out_of_stock' => $this->stockHideOOS,
            'sort' => $sort,
            'dir' => $this->stockDir,
            'page' => $this->stockPage,
            'per_page' => 20,
        ], fn ($value) => $value !== null)));

        $this->stockItems = $data['products'] ?? [];
        $this->stockLastPage = $data['pagination']['last_page'] ?? 1;

        if ($this->stockSort === 'avg_sales') {
            $this->sortStockItemsByAvgSales();
        }
    }

    public function updatedStockSearch(): void
    {
        $this->resetApiError();
        $this->stockPage = 1;
        $this->loadStock();
    }

    public function selectStockCategory(?int $id): void
    {
        $this->resetApiError();
        $this->stockCategoryId = $id;
        $this->stockCatOpen = false;
        $this->stockCategorySearch = '';
        $this->stockPage = 1;
        $this->loadStock();
    }

    public function toggleStockCategoryDropdown(): void
    {
        $this->stockCatOpen = ! $this->stockCatOpen;
        $this->stockCategorySearch = '';
    }

    public function toggleStockHideInactive(): void
    {
        $this->resetApiError();
        $this->stockHideInactive = ! $this->stockHideInactive;
        $this->stockPage = 1;
        $this->loadStock();
    }

    public function toggleStockHideOOS(): void
    {
        $this->resetApiError();
        $this->stockHideOOS = ! $this->stockHideOOS;
        $this->stockPage = 1;
        $this->loadStock();
    }

    public function setStockSort(string $column): void
    {
        if ($this->stockSort === $column) {
            $this->stockDir = $this->stockDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->stockSort = $column;
            $this->stockDir = 'asc';
        }

        if (in_array($column, self::SERVER_SORTABLE_COLUMNS, true)) {
            $this->resetApiError();
            $this->stockPage = 1;
            $this->loadStock();
        } else {
            // avg_sales: no server-side sort (see loadStock()) — reorder the
            // already-loaded page client-side instead of refetching.
            $this->sortStockItemsByAvgSales();
        }
    }

    private function sortStockItemsByAvgSales(): void
    {
        $dir = $this->stockDir;

        usort(
            $this->stockItems,
            fn (array $a, array $b) => $dir === 'asc'
                ? (($a['average_monthly_sales'] ?? 0) <=> ($b['average_monthly_sales'] ?? 0))
                : (($b['average_monthly_sales'] ?? 0) <=> ($a['average_monthly_sales'] ?? 0))
        );
    }

    public function stockPagePrev(): void
    {
        if ($this->stockPage > 1) {
            $this->resetApiError();
            $this->stockPage--;
            $this->loadStock();
        }
    }

    public function stockPageNext(): void
    {
        if ($this->stockPage < $this->stockLastPage) {
            $this->resetApiError();
            $this->stockPage++;
            $this->loadStock();
        }
    }

    public function selectStockItem(int $id): void
    {
        $this->navigate('/stock/item/product/'.$id);
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
