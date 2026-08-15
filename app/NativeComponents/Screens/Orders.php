<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Orders extends NativeComponent
{
    use HandlesApiErrors;

    /**
     * Every fetch (initial load, search, load-more) is scoped to this many
     * trailing days via the `start_date` param — see windowStartDate().
     */
    private const SEARCH_WINDOW_DAYS = 30;

    public string $search = '';

    public array $orders = [];

    public int $currentPage = 1;

    public bool $hasMorePages = false;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();
        $this->currentPage = 1;

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/orders', [
            'search' => $this->search ?: null,
            'start_date' => $this->windowStartDate(),
            'page' => 1,
        ]));

        $this->orders = $data['orders'] ?? [];
        $this->hasMorePages = ($data['pagination']['current_page'] ?? 1) < ($data['pagination']['last_page'] ?? 1);
    }

    public function loadMore(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/orders', [
            'search' => $this->search ?: null,
            'start_date' => $this->windowStartDate(),
            'page' => $this->currentPage + 1,
        ]));

        if ($data !== null) {
            $this->orders = array_merge($this->orders, $data['orders'] ?? []);
            $this->currentPage = $data['pagination']['current_page'] ?? $this->currentPage + 1;
            $this->hasMorePages = $this->currentPage < ($data['pagination']['last_page'] ?? $this->currentPage);
        }
    }

    /**
     * Human-readable label for the fixed search window, shown in the
     * empty state so a merchant understands why an older order might not
     * appear rather than reading it as "not found" or "app is broken".
     */
    public function windowLabel(): string
    {
        return self::SEARCH_WINDOW_DAYS.' derniers jours';
    }

    private function windowStartDate(): string
    {
        return now()->subDays(self::SEARCH_WINDOW_DAYS)->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->refresh();
    }

    public function selectOrder(int $id): void
    {
        $order = collect($this->orders)->firstWhere('id', $id) ?? ['id' => $id];

        $this->navigate('/orders/'.$id, ['order' => $order]);
    }

    public function render(): View
    {
        return view('native.orders');
    }
}
