<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class StockAlertsTab extends NativeComponent
{
    use HandlesApiErrors;

    public array $stats = ['total' => 0, 'low_stock' => 0, 'out_of_stock' => 0];

    public array $alerts = [];

    public ?bool $unreadOnly = null;

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

        $overview = $this->callApi(fn () => app(LumexioApi::class)->get('/alerts/stock-overview'));
        $this->stats = $overview['stats'] ?? $this->stats;

        $query = $this->unreadOnly !== null ? ['is_read' => ! $this->unreadOnly] : [];
        $alertsData = $this->callApi(fn () => app(LumexioApi::class)->get('/alerts', array_merge($query, ['type' => 'stock_low', 'per_page' => 50])));
        $this->alerts = $alertsData['alerts'] ?? [];
    }

    public function toggleUnreadOnly(): void
    {
        $this->unreadOnly = $this->unreadOnly === true ? null : true;
        $this->refresh();
    }

    public function markAsRead(int $alertId): void
    {
        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/alerts/'.$alertId.'/read'));

        if ($result !== null) {
            $this->alerts = collect($this->alerts)
                ->map(fn ($a) => $a['id'] === $alertId ? array_merge($a, ['is_read' => true]) : $a)
                ->values()
                ->all();
        }
    }

    public function markAllAsRead(): void
    {
        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/alerts/read-all'));

        if ($result !== null) {
            $this->refresh();
        }
    }

    public function render(): View
    {
        return view('native.stock-alerts-tab');
    }
}
