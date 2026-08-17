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

    public bool $loading = true;

    public ?string $syncStatus = null;

    public ?string $syncError = null;

    public int $dayScope = 0;

    public ?array $widgets = null;

    /** Shop-level forecast summary from GET /forecasts (forecast_30d, confidence, …). */
    public array $summary = [];

    /** Stock-depletion stats from GET /stock-depletion (critical, out_of_stock, …). */
    public array $stockStats = [];

    /** Customer segment stats from GET /segments/stats — list of {name, customers_count}. */
    public array $segments = [];

    /** The 3 most recent alerts, from GET /alerts?per_page=3. */
    public array $recentAlerts = [];

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
        $this->loadCurrentUser();

        $metrics = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/metrics', ['day' => $this->dayScopeValue()]));
        $widgets = $this->callApi(fn () => app(LumexioApi::class)->get('/dashboard/widgets', ['day' => $this->dayScopeValue()]));
        $forecasts = $this->callApi(fn () => app(LumexioApi::class)->get('/forecasts'));
        $stockDepletion = $this->callApi(fn () => app(LumexioApi::class)->get('/stock-depletion'));
        $segments = $this->callApi(fn () => app(LumexioApi::class)->get('/segments/stats'));
        $alerts = $this->callApi(fn () => app(LumexioApi::class)->get('/alerts', ['per_page' => 3]));
        $shops = $this->callApi(fn () => app(LumexioApi::class)->get('/shops'));

        $this->metrics = $metrics['metrics'] ?? [];
        $this->widgets = $widgets['widgets'] ?? null;
        $this->summary = $forecasts['summary'] ?? [];
        $this->stockStats = $stockDepletion['stats'] ?? [];
        $this->segments = $segments['segments'] ?? [];
        $this->recentAlerts = $alerts['alerts'] ?? [];

        $currentShopId = LocalState::current()->shop_id;
        $currentShop = filled($currentShopId)
            ? collect($shops['shops'] ?? [])->firstWhere('id', $currentShopId)
            : null;

        $this->syncStatus = $currentShop['sync_status'] ?? null;
        $this->syncError = $currentShop['sync_error'] ?? null;
        LocalState::current()->update(['active_shop_name' => $currentShop['name'] ?? LocalState::current()->active_shop_name]);

        $this->loading = false;
    }

    /**
     * Today's/yesterday's revenue as a delta vs. that day's forecast —
     * `ca_forecast_percent` (already actual ÷ forecast × 100) minus 100.
     * Null when no forecast exists for the day (never a fabricated 0%).
     */
    public function heroDeltaPercent(): ?float
    {
        $percent = $this->widgets['ca_forecast_percent'] ?? null;

        return $percent === null ? null : $percent - 100;
    }

    public function forecast30d(): float
    {
        return (float) ($this->summary['forecast_30d'] ?? 0);
    }

    public function forecastConfidence(): int
    {
        return (int) ($this->summary['confidence'] ?? 0);
    }

    public function criticalStockCount(): int
    {
        return (int) ($this->stockStats['critical'] ?? 0);
    }

    public function vipCount(): int
    {
        return (int) (collect($this->segments)->firstWhere('name', 'vip')['customers_count'] ?? 0);
    }

    public function atRiskCount(): int
    {
        return (int) (collect($this->segments)->firstWhere('name', 'at_risk')['customers_count'] ?? 0);
    }

    public function render(): View
    {
        return view('native.dashboard');
    }
}
