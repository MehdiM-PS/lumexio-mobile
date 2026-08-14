<?php

namespace App\NativeComponents\Screens;

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

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loading = true;

        $metrics = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/metrics'));
        $charts = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/charts'));
        $orders = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/recent-orders', ['limit' => 10]));
        $lowStock = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/low-stock', ['limit' => 10]));

        $this->metrics = $metrics['metrics'] ?? [];
        $this->chartLabels = $charts['revenue_margin']['labels'] ?? [];
        $this->chartRevenue = $charts['revenue_margin']['revenue'] ?? [];
        $this->recentOrders = $orders['orders'] ?? [];
        $this->lowStockProducts = $lowStock['products'] ?? [];

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
