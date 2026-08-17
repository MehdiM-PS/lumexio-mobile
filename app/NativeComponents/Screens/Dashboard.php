<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Dashboard extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    /**
     * Hour of day (matches the web dashboard's `DashboardPage::DAY_SWITCH_HOUR`
     * convention) at which the default day scope switches from yesterday (J-1)
     * to today (J). Before this hour today's figures are too partial to be
     * meaningful, so the app defaults to showing yesterday's complete data —
     * kept identical to web so a merchant sees the same default on both.
     */
    private const int DAY_SWITCH_HOUR = 14;

    public array $metrics = [];

    public array $chartLabels = [];

    public array $chartRevenue = [];

    public ?int $selectedBarIndex = null;

    public array $recentOrders = [];

    public array $lowStockProducts = [];

    public bool $loading = true;

    public ?string $syncStatus = null;

    public ?string $syncError = null;

    public int $dayScope = 0;

    public ?array $widgets = null;

    public array $topProducts = [];

    public function mount(): void
    {
        $this->dayScope = now()->hour < self::DAY_SWITCH_HOUR ? 1 : 0;

        $this->refresh();
    }

    public function dayScopeValue(): string
    {
        return $this->dayScope === 1 ? 'yesterday' : 'today';
    }

    public function setDayScope(int $index): void
    {
        $this->dayScope = $index;
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loading = true;
        $this->resetApiError();
        // Reset before the fetch so a stale index never survives into
        // freshly-fetched data with a possibly-different length.
        $this->selectedBarIndex = null;

        $metrics = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/metrics', ['day' => $this->dayScopeValue()]));
        $charts = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/charts'));
        $orders = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/recent-orders', ['limit' => 10]));
        $lowStock = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/low-stock', ['limit' => 10]));
        $widgets = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/widgets', ['day' => $this->dayScopeValue()]));
        $topProducts = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/top-products', ['day' => $this->dayScopeValue()]));
        $shops = $this->callApi(fn () => app(LumexioApi::class)->get('/shops'));

        $this->metrics = $metrics['metrics'] ?? [];
        $this->chartLabels = $charts['revenue_margin']['labels'] ?? [];
        $this->chartRevenue = $charts['revenue_margin']['revenue'] ?? [];
        $this->recentOrders = $orders['orders'] ?? [];
        $this->lowStockProducts = $lowStock['products'] ?? [];
        $this->widgets = $widgets['widgets'] ?? null;
        $this->topProducts = $topProducts['products'] ?? [];

        $currentShopId = LocalState::current()->shop_id;
        $currentShop = filled($currentShopId)
            ? collect($shops['shops'] ?? [])->firstWhere('id', $currentShopId)
            : null;

        $this->syncStatus = $currentShop['sync_status'] ?? null;
        $this->syncError = $currentShop['sync_error'] ?? null;

        $this->loading = false;
    }

    public function formattedRevenuePeriod(): string
    {
        return number_format($this->metrics['revenue_period'] ?? 0, 0, ',', ' ').' €';
    }

    public function barHeight(float $value): int
    {
        $max = max($this->chartRevenue ?: [1]);

        return $max > 0 ? max(4, (int) round(($value / $max) * 72)) : 4;
    }

    public function selectBar(int $index): void
    {
        $this->selectedBarIndex = $this->selectedBarIndex === $index ? null : $index;
    }

    public function render(): View
    {
        return view('native.dashboard');
    }
}
