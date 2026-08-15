<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Orders extends NativeComponent
{
    use HandlesApiErrors;

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
            'start_date' => now()->subDays(30)->toDateString(),
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
            'start_date' => now()->subDays(30)->toDateString(),
            'page' => $this->currentPage + 1,
        ]));

        if ($data !== null) {
            $this->orders = array_merge($this->orders, $data['orders'] ?? []);
            $this->currentPage = $data['pagination']['current_page'] ?? $this->currentPage + 1;
            $this->hasMorePages = $this->currentPage < ($data['pagination']['last_page'] ?? $this->currentPage);
        }
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
