<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Dashboard extends NativeComponent
{
    use HandlesApiErrors;

    public array $metrics = [];

    public array $chartLabels = [];

    public array $chartRevenue = [];

    public array $recentOrders = [];

    public array $lowStockProducts = [];

    public bool $loading = true;

    public ?string $syncStatus = null;

    public ?string $syncError = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loading = true;
        $this->resetApiError();

        $metrics = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/metrics'));
        $charts = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/charts'));
        $orders = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/recent-orders', ['limit' => 10]));
        $lowStock = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/low-stock', ['limit' => 10]));
        $shops = $this->callApi(fn () => app(LumexioApi::class)->get('/shops'));

        $this->metrics = $metrics['metrics'] ?? [];
        $this->chartLabels = $charts['revenue_margin']['labels'] ?? [];
        $this->chartRevenue = $charts['revenue_margin']['revenue'] ?? [];
        $this->recentOrders = $orders['orders'] ?? [];
        $this->lowStockProducts = $lowStock['products'] ?? [];

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

    public function render(): View
    {
        return view('native.dashboard');
    }
}
